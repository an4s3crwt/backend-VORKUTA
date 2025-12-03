import sys
import json
import os
import pandas as pd
import numpy as np
import joblib
from datetime import datetime

# --- 1. CONFIGURACIÓN ---
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, 'flighty_web_model.joblib')
SCALER_PATH = os.path.join(BASE_DIR, 'flighty_web_scaler.joblib')

def load_resources():
    try:
        if not os.path.exists(MODEL_PATH) or not os.path.exists(SCALER_PATH):
            return None, None
        model = joblib.load(MODEL_PATH)
        scaler = joblib.load(SCALER_PATH)
        return model, scaler
    except Exception:
        return None, None

model, scaler = load_resources()

# --- 2. LÓGICA DE PREDICCIÓN ---
def predict_delay(data_history):
    if model is None:
        return {'success': False, 'error': "Archivos de modelo no encontrados"}

    try:
        # A. PREPARAR DATOS
        df_hist = pd.DataFrame(data_history)
        cols = ['latitude', 'longitude', 'velocity', 'heading', 'baro_altitude', 'geo_altitude', 'vertical_rate']
        
        for c in cols:
            if c not in df_hist.columns:
                df_hist[c] = 0.0
            df_hist[c] = df_hist[c].astype(float)

        current = df_hist.iloc[-1]
        
        # Variables actuales
        curr_vel = float(current['velocity'])      
        curr_alt = float(current['geo_altitude']) 
        curr_ground = bool(current['on_ground'])

        # --- 🕵️‍♂️ DETECTOR 1: DATOS CONGELADOS (Calculamos, pero NO retornamos aquí) ---
        is_stale = False
        if len(df_hist) > 1:
            prev = df_hist.iloc[-2]
            if (current['latitude'] == prev['latitude'] and current['longitude'] == prev['longitude']):
                is_stale = True

        # --- 🛑 REGLA 2 (PRIORIDAD MÁXIMA): EN TIERRA / PREPARANDO (AZUL) ---
        if (curr_vel < 5.0 and curr_alt < 50.0) or (curr_ground and curr_vel < 2.0):
            msg = "Aircraft parked at gate." if curr_vel < 1.0 else "Aircraft taxiing / Ground Ops."
            return {
                'success': True,
                'delay_minutes': 0.0,
                'status': 'scheduled', 
                'predicted_probability': 0.0,
                'explanation': msg,
                'calculated_at': datetime.now().isoformat()
            }
        
        # ==============================================================================
        # CÁLCULOS Y PREDICCIÓN ML
        # ==============================================================================

        # B. CÁLCULOS ESTADÍSTICOS
        vel_mean = df_hist['velocity'].mean()
        vel_std  = df_hist['velocity'].std() if len(df_hist) > 1 else 0.0
        if pd.isna(vel_std): vel_std = 0.0

        alt_mean = df_hist['geo_altitude'].mean()
        alt_std  = df_hist['geo_altitude'].std() if len(df_hist) > 1 else 0.0
        if pd.isna(alt_std): alt_std = 0.0

        vert_std = df_hist['vertical_rate'].std() if len(df_hist) > 1 else 0.0
        if pd.isna(vert_std): vert_std = 0.0
        
        if len(df_hist) > 1:
            prev = df_hist.iloc[-2]
            heading_change = abs(current['heading'] - prev['heading'])
            dist_moved = np.sqrt((current['latitude']-prev['latitude'])**2 + (current['longitude']-prev['longitude'])**2)
        else:
            heading_change = 0.0
            dist_moved = 0.0

        heading_change_cumsum = df_hist['heading'].diff().abs().sum()
        if pd.isna(heading_change_cumsum): heading_change_cumsum = 0.0

        # C. PREPARAR VECTOR DE IA
        vertical_rate = float(current['vertical_rate'])
        hour = int(datetime.now().hour)

        is_ground = 1 if curr_ground else 0
        is_descent = 1 if (not curr_ground and vertical_rate < -2.0) else 0
        is_cruise  = 1 if (not curr_ground and -2.0 <= vertical_rate <= 2.0) else 0

        features = {
            'latitude': current['latitude'], 'longitude': current['longitude'], 'velocity': current['velocity'], 
            'heading': current['heading'], 'baro_altitude': current['baro_altitude'], 'geo_altitude': current['geo_altitude'],
            'heading_change': heading_change, 'heading_change_cumsum': heading_change_cumsum, 'dist_moved': dist_moved, 
            'velocity_mean': vel_mean, 'altitude_mean': alt_mean, 'velocity_std': vel_std, 'altitude_std': alt_std, 
            'vertical_rate_std': vert_std, 'hour': hour, 'phase_CRUISE': is_cruise, 'phase_DESCENT': is_descent, 'phase_GROUND': is_ground
        }
        
        cols_order = ['latitude', 'longitude', 'velocity', 'heading', 'baro_altitude', 'geo_altitude', 'heading_change', 'heading_change_cumsum', 'dist_moved', 'velocity_mean', 'altitude_mean', 'velocity_std', 'altitude_std', 'vertical_rate_std', 'hour', 'phase_CRUISE', 'phase_DESCENT', 'phase_GROUND']
        
        df = pd.DataFrame([features], columns=cols_order)
        X_scaled = scaler.transform(df)

        # D. PREDICCIÓN IA
        prediction_class = model.predict(X_scaled)[0]
        confidence = model.decision_function(X_scaled)[0]
        probability = 1 / (1 + np.exp(-confidence))

        # --- 🛡️ SAFETY NET ---
        is_stable_flight = (curr_alt > 3000) and (curr_vel > 100)
        is_normal_approach = (curr_alt < 3000) and (curr_vel > 50) and (is_descent or is_cruise)

        explanation_override = None
        is_high_risk = False
        minutes = 0.0
        
        if is_stable_flight:
            # 1. CRUCERO ESTABLE: (VERDE)
            probability = 0.05
            explanation_override = f"Stable flight at {round(curr_alt*3.28084)}ft. On schedule."
            minutes = 0.0
        
        elif is_normal_approach:
            # 2. ATERRIZAJE/APROXIMACIÓN FINAL (Verde suave)
            probability = 0.3 
            explanation_override = f"Final approach at {round(curr_alt*3.28084)}ft. Landing imminent."
            minutes = 2.0
            
        else:
            # 3. CASOS RAROS (La IA decide)
            is_high_risk = (prediction_class == 1) and (probability > 0.6)
            if is_high_risk:
                minutes = 15 + (probability * 30)
            else:
                minutes = probability * 5

        # E. RESPUESTA FINAL
        explanation = ""
        # 1. PRIORIDAD: Si estaba estable o aterrizando, usamos ese mensaje
        if explanation_override:
            explanation = explanation_override
            status = 'on_time' if is_stable_flight or is_normal_approach else status
        # 2. SEGUNDA PRIORIDAD: Si está estático/congelado Y no es vuelo estable, damos la advertencia
        elif is_stale:
             explanation = "Signal lost or data not updating (Frozen Radar)."
             status = 'potential_delay'
             minutes = 0.0
        # 3. TERCERA PRIORIDAD: El riesgo calculado por la IA
        elif is_high_risk:
            explanation = f"High risk detected. Delay: {minutes} min." 
            status = 'delayed'
        else:
            explanation = "Operations normal."
            status = 'on_time'

        # Definimos el estado final
        if is_high_risk: status = 'delayed'
        elif probability > 0.4 and not is_stable_flight: status = 'potential_delay' # Si no es estable y hay riesgo
        else: status = 'on_time'

        return {
            'success': True,
            'delay_minutes': round(float(minutes), 1),
            'status': status,
            'predicted_probability': round(float(probability), 2),
            'explanation': explanation,
            'calculated_at': datetime.now().isoformat()
        }

    except Exception as e:
        return {'success': False, 'error': f"Python Error: {str(e)}"}

if __name__ == "__main__":
    try:
        if len(sys.argv) < 2:
            raise ValueError("No input data")
        input_data = json.loads(sys.argv[1])
        print(json.dumps(predict_delay(input_data)))
    except Exception as e:
        print(json.dumps({'success': False, 'error': str(e)}))
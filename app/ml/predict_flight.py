import joblib
import sys
import json
import pandas as pd
import numpy as np
import os

# --- 1. Rutas de los Modelos Serializados ---
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_FILE = os.path.join(BASE_DIR, 'flighty_web_model.joblib')
SCALER_FILE = os.path.join(BASE_DIR, 'flighty_web_scaler.joblib')

# --- 2. Definición del Orden de Features ---
FEATURE_ORDER = [
    'latitude', 'longitude', 'velocity', 'heading', 'baro_altitude', 'geo_altitude', 
    'heading_change', 'heading_change_cumsum', 'dist_moved', 'velocity_mean', 
    'altitude_mean', 'velocity_std', 'altitude_std', 'vertical_rate_std', 
    'hour_sin', 'hour_cos', 'phase_air', 'phase_ground'
]

# --- Funciones Auxiliares ---
def stddev(arr):
    arr = np.array(arr)
    return float(np.sqrt(np.mean((arr - np.mean(arr))**2)))

def haversine(lat1, lon1, lat2, lon2):
    R = 6371  # km
    dLat = np.radians(lat2 - lat1)
    dLon = np.radians(lon2 - lon1)
    a = np.sin(dLat/2)**2 + np.cos(np.radians(lat1)) * np.cos(np.radians(lat2)) * np.sin(dLon/2)**2
    c = 2 * np.arctan2(np.sqrt(a), np.sqrt(1-a))
    return R * c * 1000  # metros

def generate_min_history(current, min_points=3):
    # time_position seguro
    time_pos = current.get('time_position', int(pd.Timestamp.now().timestamp()))
    vertrate_val = current.get('vertrate', 0)
    
    history = []
    for i in range(min_points-1):
        fake = {
            'latitude': current['latitude'] + np.random.uniform(-0.01, 0.01),
            'longitude': current['longitude'] + np.random.uniform(-0.01, 0.01),
            'velocity': current['velocity'] * np.random.uniform(0.95, 1.05),
            'heading': (current['heading'] + np.random.uniform(-5, 5)) % 360,
            'geo_altitude': current['geo_altitude'] * np.random.uniform(0.95, 1.05),
            'baro_altitude': current['baro_altitude'] * np.random.uniform(0.95, 1.05),
            'vertrate': vertrate_val + np.random.uniform(-2, 2),
            'phase': current.get('phase', 'air'),
            'time_position': time_pos - (min_points-i)*10
        }
        history.append(fake)
    history.append(current)
    return history

def compute_features(history):
    n = len(history)
    last = history[-1]
    
    heading_change = 0
    heading_cumsum = 0
    dist_moved = 0
    velocities = []
    altitudes = []
    vertical_rates = []

    for i in range(n):
        velocities.append(history[i]['velocity'])
        altitudes.append(history[i]['geo_altitude'])
        vertical_rates.append(history[i].get('vertrate', 0))
        if i > 0:
            delta_heading = abs(history[i]['heading'] - history[i-1]['heading'])
            heading_change = delta_heading
            heading_cumsum += delta_heading
            dist_moved += haversine(history[i-1]['latitude'], history[i-1]['longitude'],
                                    history[i]['latitude'], history[i]['longitude'])

    time_pos = last.get('time_position', int(pd.Timestamp.now().timestamp()))
    hour = pd.to_datetime(time_pos, unit='s').hour

    return {
        'latitude': last['latitude'],
        'longitude': last['longitude'],
        'velocity': last['velocity'],
        'heading': last['heading'],
        'baro_altitude': last['baro_altitude'],
        'geo_altitude': last['geo_altitude'],
        'heading_change': heading_change,
        'heading_change_cumsum': heading_cumsum,
        'dist_moved': dist_moved,
        'velocity_mean': np.mean(velocities),
        'altitude_mean': np.mean(altitudes),
        'velocity_std': stddev(velocities),
        'altitude_std': stddev(altitudes),
        'vertical_rate_std': stddev(vertical_rates),
        'hour_sin': np.sin(2 * np.pi * hour / 24),
        'hour_cos': np.cos(2 * np.pi * hour / 24),
        'phase_air': 1 if last.get('phase','air')=='air' else 0,
        'phase_ground': 1 if last.get('phase','air')=='ground' else 0
    }

# --- MAIN ---
if __name__ == "__main__":
    try:
        input_data = json.loads(sys.argv[1])
        features = input_data['features']

        # Generar historial mínimo
        history = generate_min_history(features)

        # Calcular features finales
        final_features = compute_features(history)

        # Cargar modelo y escalador
        model = joblib.load(MODEL_FILE)
        scaler = joblib.load(SCALER_FILE)

        # Escalar y ordenar features
        df_input = pd.DataFrame([final_features])
        X_input_scaled = scaler.transform(df_input[FEATURE_ORDER].values)

        # Predicción
        prediction_score = model.decision_function(X_input_scaled)[0]
        estimated_delay_minutes = max(0, min(int(prediction_score * 45), 180))  # cap 180 min

        # Salida
        print(json.dumps({
            "status": "success",
            "predicted_risk_score": prediction_score,
            "estimated_delay_minutes": estimated_delay_minutes
        }))

    except Exception as e:
        print(json.dumps({
            "status": "error",
            "message": f"Fallo al procesar la predicción: {str(e)}"
        }))
        sys.exit(1)

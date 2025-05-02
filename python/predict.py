import sys
import json
import joblib
import pandas as pd
from datetime import datetime

# Configuración de paths (ajusta según tu estructura)
MODEL_PATH = 'C:/Users/Usuario/modelo_retrasos.pkl'
PREPROCESSOR_PATH = 'C:/Users/Usuario/preprocesador.pkl'
STATS_PATH = 'C:/Users/Usuario/Data/estadisticas_rutas.csv'

def load_resources():
    """Carga todos los recursos necesarios una sola vez"""
    try:
        model = joblib.load(MODEL_PATH)
        preprocessor = joblib.load(PREPROCESSOR_PATH)
        route_stats = pd.read_csv(STATS_PATH)
        return model, preprocessor, route_stats
    except Exception as e:
        print(f"Error cargando recursos: {str(e)}", file=sys.stderr)
        sys.exit(1)

# Carga global de recursos al iniciar
model, preprocessor, route_stats = load_resources()

def predict_delay(flight_data):
    """Función principal de predicción"""
    try:
        # 1. Preparar DataFrame con todas las features necesarias
        flight_features = {
            'from_airport_code': flight_data.get('origin'),
            'dest_airport_code': flight_data.get('destination'),
            'airline_name': flight_data.get('airline'),
            'departure_time': datetime.now().isoformat(),
            'route': f"{flight_data.get('origin')}-{flight_data.get('destination')}",
            'departure_hour': datetime.now().hour,
            'day_of_week': datetime.now().weekday(),
            'month': datetime.now().month,
            'avg_delay_route': get_route_stats(flight_data['origin'], flight_data['destination'], 'mean'),
            'std_delay_route': get_route_stats(flight_data['origin'], flight_data['destination'], 'std'),
            'avg_delay_airline': get_airline_stats(flight_data['airline'], 'mean'),
            'std_delay_airline': get_airline_stats(flight_data['airline'], 'std')
        }
        
        # 2. Convertir a DataFrame
        new_flight = pd.DataFrame([flight_features])
        
        # 3. Preprocesar
        processed_data = preprocessor.transform(new_flight)
        
        # 4. Predecir
        delay = model.predict(processed_data)[0]
        
        # 5. Formatear resultado
        return {
            'success': True,
            'delay_minutes': round(float(delay), 1),
            'status': 'on_time' if delay <= 0 else 'delayed',
            'calculated_at': datetime.now().isoformat()
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': str(e),
            'input_data': flight_data
        }

def get_route_stats(origin, destination, stat_type='mean'):
    """Obtiene estadísticas de la ruta"""
    route = f"{origin}-{destination}"
    stats = route_stats[route_stats['route'] == route]
    if stats.empty:
        return route_stats['delay_minutes'].mean() if stat_type == 'mean' else route_stats['delay_minutes'].std()
    return stats[f'delay_{stat_type}'].values[0]

def get_airline_stats(airline, stat_type='mean'):
    """Obtiene estadísticas de la aerolínea"""
    stats = route_stats[route_stats['airline_name'] == airline]
    if stats.empty:
        return route_stats['delay_minutes'].mean() if stat_type == 'mean' else route_stats['delay_minutes'].std()
    return stats[f'delay_{stat_type}'].values[0]

if __name__ == "__main__":
    # Entrada desde Laravel vía STDIN
    try:
        input_data = json.loads(sys.argv[1])
        result = predict_delay(input_data)
        print(json.dumps(result))
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': f"Error en ejecución: {str(e)}"
        }))
        sys.exit(1)
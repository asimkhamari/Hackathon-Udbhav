import pandas as pd
import matplotlib.pyplot as plt
from sklearn.model_selection import train_test_split, RandomizedSearchCV, StratifiedKFold, cross_val_score
from sklearn.dummy import DummyClassifier
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import f1_score
import shap
from sqlalchemy import create_engine, text, inspect
from pathlib import Path
import numpy as np
import joblib
from datetime import datetime
import json
import sys
import argparse

DB_USER = "root"
DB_PASSWORD = ""
DB_HOST = "localhost"
DB_PORT = "3306"
DB_NAME = "campus_entity_system"

SWIPES_TABLE = "card_swipes"
PROFILES_TABLE = "entities"
SWIPES_ENTITY_COL = "entity_id"
PROFILES_ENTITY_COL = "entity_id"
SWIPES_CARD_COL = "card_id"
PROFILES_CARD_COL = "card_id"

TARGET_COLUMN = "location_id"
FEATURE_COLUMN = "timestamp"

MODEL_PATH = Path("trained_location_model.joblib")
COLUMNS_PATH = Path("model_feature_columns.json")

def run_validation_checks(df: pd.DataFrame):
    if df is None or df.empty:
        print("DataFrame is empty. Skipping validation checks.")
        return

    print("\n--- Starting Data Validation ---")
    total_nulls = df.isnull().sum().sum()
    if total_nulls > 0:
        print(f"  [CHECK] FAIL: Found {total_nulls} null values in the dataset!")
    else:
        print("  [CHECK] PASS: Your dataset is clean with no null values.")
    print("--- Data Validation Complete ---")

def load_and_prepare_data(target_column: str, feature_column: str) -> tuple:
    print(f"\n--- Connecting to Database '{DB_NAME}' ---")
    try:
        connection_string = f"mysql+mysqlconnector://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
        engine = create_engine(connection_string)
        print("  Successfully connected to the database.")
    except Exception as e:
        print(f"  Error: Could not connect to the database. Details: {e}")
        return None, None

    inspector = inspect(engine)
    try:
        swipe_cols = [c['name'] for c in inspector.get_columns(SWIPES_TABLE)]
    except Exception:
        swipe_cols = []
    try:
        profile_cols = [c['name'] for c in inspector.get_columns(PROFILES_TABLE)]
    except Exception:
        profile_cols = []

    print(f"  Detected columns in {SWIPES_TABLE}: {swipe_cols}")
    print(f"  Detected columns in {PROFILES_TABLE}: {profile_cols}")

    select_fields = [f"cs.location_id AS {target_column}", f"cs.{feature_column}"]
    if SWIPES_CARD_COL in swipe_cols:
        select_fields.append(f"cs.{SWIPES_CARD_COL} AS card_id")
    if SWIPES_ENTITY_COL in swipe_cols:
        select_fields.append(f"cs.{SWIPES_ENTITY_COL} AS entity_id")
    if 'role' in profile_cols:
        select_fields.append("p.role")
    if 'department' in profile_cols:
        select_fields.append("p.department")

    join_conds = []
    if (SWIPES_ENTITY_COL in swipe_cols) and (PROFILES_ENTITY_COL in profile_cols):
        join_conds.append(f"(cs.{SWIPES_ENTITY_COL} = p.{PROFILES_ENTITY_COL})")
    if (SWIPES_CARD_COL in swipe_cols) and (PROFILES_CARD_COL in profile_cols):
        join_conds.append(f"(cs.{SWIPES_CARD_COL} = p.{PROFILES_CARD_COL})")

    if join_conds:
        join_sql = " OR ".join(join_conds)
        sql_query = f"""
        SELECT
            {',\n            '.join(select_fields)}
        FROM
            {SWIPES_TABLE} cs
        LEFT JOIN
            {PROFILES_TABLE} p
            ON ({join_sql})
        """
    else:
        sql_query = f"""
        SELECT
            {',\n            '.join(select_fields)}
        FROM
            {SWIPES_TABLE} cs
        """

    print("  Executing SQL query to fetch data...")
    try:
        count_sql = f"SELECT COUNT(*) as cnt FROM {SWIPES_TABLE}"
        with engine.connect() as conn:
            total_rows = conn.execute(text(count_sql)).scalar()
    except Exception:
        total_rows = None

    df = pd.read_sql(sql_query, engine)
    print(f"  Successfully loaded {len(df)} rows of data from query.")
    if total_rows is not None:
        print(f"  Total rows in '{SWIPES_TABLE}' according to DB: {total_rows}")

    if total_rows is not None and len(df) < total_rows:
        print("  Warning: joined query returned fewer rows than the swipes table. Falling back to full-table load + pandas merge to preserve all rows.")
        try:
            swipes_full = pd.read_sql(f"SELECT * FROM {SWIPES_TABLE}", engine)

            prof_needed = [c for c in [PROFILES_ENTITY_COL, PROFILES_CARD_COL, 'role', 'department'] if c in profile_cols]
            if prof_needed:
                profiles_sql = f"SELECT {', '.join(prof_needed)} FROM {PROFILES_TABLE}"
                profiles_df = pd.read_sql(profiles_sql, engine)
            else:
                profiles_df = pd.DataFrame()

            df = swipes_full.copy()

            merged = False
            if (SWIPES_ENTITY_COL in df.columns) and (PROFILES_ENTITY_COL in profiles_df.columns):
                df = df.merge(profiles_df, left_on=SWIPES_ENTITY_COL, right_on=PROFILES_ENTITY_COL, how='left')
                merged = True

            if (SWIPES_CARD_COL in df.columns) and (PROFILES_CARD_COL in profiles_df.columns):
                temp = swipes_full.merge(profiles_df, left_on=SWIPES_CARD_COL, right_on=PROFILES_CARD_COL, how='left', suffixes=('', '_card'))
                for col in ['role', 'department']:
                    card_col = col + '_card'
                    if card_col in temp.columns:
                        if col in df.columns:
                            df[col] = df[col].fillna(temp[card_col])
                        else:
                            df[col] = temp[card_col]

            print(f"  After fallback merge, rows: {len(df)}")
        except Exception as e:
            print(f"  Fallback full-load failed: {e}")

    run_validation_checks(df)
    
    print("\n--- Engineering Features ---")
    
    df[feature_column] = pd.to_datetime(df[feature_column], errors='coerce')
    before_drop = len(df)
    df.dropna(subset=[feature_column], inplace=True)
    after_drop = len(df)
    dropped = before_drop - after_drop
    print(f"  Rows with invalid/missing {feature_column} dropped: {dropped} (remaining: {after_drop})")
    
    df['hour'] = df[feature_column].dt.hour
    df['dayofweek'] = df[feature_column].dt.dayofweek
    df['is_weekend'] = (df[feature_column].dt.weekday >= 5).astype(int)

    y = df[target_column]
    X = df.drop(columns=[target_column, feature_column, 'card_id'], errors='ignore')
    
    for col in ['role', 'department']:
        if col not in X.columns:
            X[col] = 'unknown'
        else:
            X[col] = X[col].fillna('unknown')

    dummy_cols = ['role', 'department']
    X = pd.get_dummies(X, columns=dummy_cols, drop_first=True)
    
    print("  Successfully created features from time and profile data.")
    print(f"  Final features for model: {X.columns.tolist()}")
    print(f"  Feature matrix shape: {X.shape}; Target vector length: {len(y)}")
    
    try:
        print(f"  Target class distribution:\n{y.value_counts(dropna=False).to_string()}")
    except Exception:
        pass
    
    return X, y

def train_save_and_evaluate_model(X: pd.DataFrame, y: pd.Series):
    print("\n--- Splitting Data (80% Train, 20% Test) with stratify ---")
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)
    
    try:
        print(f"  Total feature rows: {X.shape[0]}")
        print(f"  Total target rows: {len(y)}")
        print(f"  X_train shape: {X_train.shape}; X_test shape: {X_test.shape}")
        print(f"  y_train distribution:\n{y_train.value_counts(dropna=False).to_string()}")
        print(f"  y_test distribution:\n{y_test.value_counts(dropna=False).to_string()}")
    except Exception:
        pass

    print("\n--- Baseline: Dummy (most frequent) ---")
    dummy = DummyClassifier(strategy='most_frequent')
    dummy.fit(X_train, y_train)
    dummy_f1 = f1_score(y_test, dummy.predict(X_test), average='macro')
    print(f"  Dummy Macro F1 (most_frequent) on test: {dummy_f1:.4f}")

    print("\n--- Hyperparameter tuning: RandomizedSearchCV for RandomForest ---")
    param_dist = { 'n_estimators': [100, 200, 500], 'max_depth': [None, 10, 20], 'class_weight': [None, 'balanced'] }
    base = RandomForestClassifier(random_state=42)
    cv = StratifiedKFold(n_splits=3, shuffle=True, random_state=42)
    search = RandomizedSearchCV(estimator=base, param_distributions=param_dist, n_iter=8, scoring='f1_macro', cv=cv, random_state=42, n_jobs=-1, verbose=0)
    search.fit(X_train, y_train)
    best = search.best_estimator_
    print(f"  Best params: {search.best_params_}")

    print("\n--- Evaluating Best Model on Test Set ---")
    predictions = best.predict(X_test)
    f1 = f1_score(y_test, predictions, average='macro')
    print(f"  Best model Macro F1 on the test set is: {f1:.4f}")

    print("\n--- Saving Model and Feature Columns ---")
    joblib.dump(best, MODEL_PATH)
    with open(COLUMNS_PATH, 'w') as f:
        json.dump(X.columns.tolist(), f)
    print(f"  Model saved to: {MODEL_PATH}")
    print(f"  Feature columns saved to: {COLUMNS_PATH}")

def load_model_and_artifacts() -> tuple:
    if not MODEL_PATH.exists() or not COLUMNS_PATH.exists():
        print(" Error: Model or column file not found.")
        print("Please run the script in 'train' mode first to create these files.")
        return None, None
        
    print(f"--- Loading model from {MODEL_PATH} ---")
    model = joblib.load(MODEL_PATH)
    
    print(f"--- Loading feature columns from {COLUMNS_PATH} ---")
    with open(COLUMNS_PATH, 'r') as f:
        model_columns = json.load(f)
        
    return model, model_columns

def get_profile_by_card_id(card_id: str):
    try:
        connection_string = f"mysql+mysqlconnector://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
        engine = create_engine(connection_string)
        sql = "SELECT role, department FROM entities WHERE card_id = :card_id LIMIT 1"
        with engine.connect() as conn:
            result = conn.execute(text(sql), {"card_id": card_id}).fetchone()
            if result:
                return result[0], result[1]
    except Exception as e:
        print(f"  Error fetching profile for card_id={card_id}: {e}")

    return None, None

def get_profile_by_entity_id(entity_id: str):
    try:
        connection_string = f"mysql+mysqlconnector://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}"
        engine = create_engine(connection_string)
        sql = "SELECT role, department, card_id FROM entities WHERE entity_id = :entity_id LIMIT 1"
        with engine.connect() as conn:
            result = conn.execute(text(sql), {"entity_id": entity_id}).fetchone()
        if result:
            return result[0], result[1], result[2]
    except Exception as e:
        print(f"  Error fetching profile for entity_id={entity_id}: {e}")

    return None, None, None

def predict_for_time_range(model, model_columns, start_time_str, end_time_str, role, department):
    try:
        time_range = pd.date_range(start=start_time_str, end=end_time_str, freq='h')
        if time_range.empty:
            print(" Error: The start time must be before the end time.")
            return
    except Exception as e:
        print(f" Error: Invalid date format. Please use 'YYYY-MM-DD HH:MM'. Details: {e}")
        return

    print(f"\n--- Predicting locations from {start_time_str} to {end_time_str} ---")
    
    prediction_data = []

    for ts in time_range:
        hour = ts.hour
        dayofweek = ts.dayofweek
        is_weekend = 1 if dayofweek >= 5 else 0

        feature_dict = {
            'hour': hour,
            'dayofweek': dayofweek,
            'is_weekend': is_weekend,
            f'role_{role}': 1,
            f'department_{department}': 1
        }
        prediction_data.append(feature_dict)

    df_predict = pd.DataFrame(prediction_data)
    df_aligned = pd.DataFrame(columns=model_columns)
    df_aligned = pd.concat([df_aligned, df_predict], ignore_index=True, sort=False)
    
    for col in df_aligned.columns:
        if df_aligned[col].isna().any():
            df_aligned[col] = df_aligned[col].fillna(0)
    
    df_aligned = df_aligned[model_columns]

    predictions = model.predict(df_aligned)
    
    location_counts = pd.Series(predictions).value_counts(normalize=True)
    
    print("\n--- Prediction Results ---")
    most_likely_location = location_counts.index[0]
    likelihood = location_counts.iloc[0]
    
    print(f"The most probable location is: **Location {most_likely_location}**")
    print(f"(Predicted to be the location in {likelihood:.0%} of the instances within the time range).")
    
    print("\nFull breakdown:")
    for loc, perc in location_counts.items():
        print(f"  - Location {loc}: {perc:.1%}")
    
    return most_likely_location, likelihood, dict(location_counts.items())

if __name__ == "__main__":
    
    print("--- Location Prediction Model ---")
    
    if len(sys.argv) > 1 and sys.argv[1] == "predict":
        if len(sys.argv) != 5:
            print("ERROR: Usage: python data_with_input.py predict 'start_time' 'end_time' 'entity_id_or_card_id'")
            sys.exit(1)

        start_time = sys.argv[2]
        end_time = sys.argv[3]
        identifier = sys.argv[4]

        fetched_role, fetched_department, fetched_card = get_profile_by_entity_id(identifier)
        if fetched_role and fetched_department:
            role = fetched_role
            department = fetched_department
        else:
            fetched_role, fetched_department = get_profile_by_card_id(identifier)
            if fetched_role and fetched_department:
                role = fetched_role
                department = fetched_department
            else:
                print(json.dumps({"status": "error", "message": f"Profile not found for identifier={identifier}"}))
                sys.exit(1)

        print("\n--- Starting Location Prediction (PHP Mode) ---")
        model, columns = load_model_and_artifacts()

        if model and columns:
            result = predict_for_time_range(model, columns, start_time, end_time, role, department)
            if result:
                most_likely, confidence, breakdown = result
                output = {
                    "most_likely_location": most_likely,
                    "confidence": confidence,
                    "breakdown": breakdown,
                    "status": "success"
                }
                print(json.dumps(output))
            else:
                print(json.dumps({"status": "error", "message": "Prediction failed"}))
    
    else:
        mode = input("Choose mode: (1) Train Model or (2) Predict Location? [1/2]: ")

        if mode == '1':
            print("\n--- Starting Model Training ---")
            features, target = load_and_prepare_data(target_column=TARGET_COLUMN, feature_column=FEATURE_COLUMN)
            
            if features is not None and target is not None:
                train_save_and_evaluate_model(X=features, y=target)
                print("\n--- Training Pipeline Complete! ---")
        
        elif mode == '2':
            print("\n--- Starting Location Prediction ---")
            model, columns = load_model_and_artifacts()

            if model and columns:
                print("\nPlease provide the details for prediction.")
                print("Example Date Format: 2025-10-09 14:00")
                start = input("Enter start time (YYYY-MM-DD HH:MM): ")
                end = input("Enter end time (YYYY-MM-DD HH:MM): ")

                identifier = input("Enter entity_id or card_id (required): ").strip()
                if not identifier:
                    print("Identifier is required for prediction. Aborting.")
                else:
                    fetched_role, fetched_department, fetched_card = get_profile_by_entity_id(identifier)
                    if fetched_role and fetched_department:
                        role = fetched_role
                        department = fetched_department
                        print(f"  Found profile for entity_id={identifier}: role={role}, department={department}")
                        predict_for_time_range(model, columns, start, end, role, department)
                    else:
                        fetched_role, fetched_department = get_profile_by_card_id(identifier)
                        if fetched_role and fetched_department:
                            role = fetched_role
                            department = fetched_department
                            print(f"  Found profile for card_id={identifier}: role={role}, department={department}")
                            predict_for_time_range(model, columns, start, end, role, department)
                        else:
                            print(f"  No profile found for identifier={identifier}. Cannot proceed without role and department.")

        else:
            print("Invalid choice. Please run the script again and enter '1' or '2'.")
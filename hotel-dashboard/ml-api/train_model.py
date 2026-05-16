"""
LSTM Model Training Module
Replicates the full Data_Hotel.ipynb → ModelLSTM_Single/Multi.ipynb pipeline.

Key insight from 2021_2025_Clean1.xlsx:
- The Excel has 35 columns: 30 engineered features + 4 room targets + Date
- ALL values are already MinMaxScaler normalized (0-1)
- The scaler was fitted on ALL 30 features + targets together on the full dataset
- The 15 features used by the model are SELECTED from those 30 normalized columns

Retraining pipeline:
1. Build monthly DataFrame from raw DB data (mirrors Data_Hotel.ipynb aggregation)
2. Engineer ALL 30 features (not just 15) — same as notebook
3. Fit MinMaxScaler on the full feature+target matrix (same as notebook)
4. Select the 15 model features from the normalized matrix
5. Split 80-10-10 chronologically
6. Create sequences with create_sequences / create_with_borrow
7. Train with best hyperparams (patience=30, batch=4)
8. Evaluate on test set
"""

import os
import calendar
import numpy as np

os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'


# ─────────────────────────────────────────────────────────────────────────────
# Constants — must match notebook exactly
# ─────────────────────────────────────────────────────────────────────────────

TOTAL_ROOMS = 58   # notebook: TOTAL_ROOMS = 58
SEQ_LEN     = 6

# 30 feature columns from Data_Hotel.ipynb (in notebook order)
ALL_FEATURES_30 = [
    'month_sin', 'month_cos', 'quarter', 'is_peak_season', 'is_low_season',
    'time_index',
    'occ_lag_1', 'occ_lag_3', 'occ_lag_6', 'occ_lag_12',
    'revenue_lag_1', 'revenue_lag_3',
    'occ_rolling_mean_3', 'occ_rolling_mean_6',
    'occ_rolling_std_3', 'occ_rolling_std_6',
    'occ_trend', 'occ_momentum', 'is_increasing',
    'available_room_nights', 'adr', 'revpar',
    'std_proportion', 'spr_proportion', 'fmy_proportion', 'js_proportion',
    'occ_yoy', 'revenue_yoy',
    'Kamar_Terjual', 'Okupansi_Rate',
]

# 4 target columns
TARGETS = ['Kamar_STD', 'Kamar_SPR', 'Kamar_FMY', 'Kamar_JS']

# 15 features selected for the model (from ModelLSTM notebooks)
FEATURES_15 = [
    'Kamar_Terjual', 'Okupansi_Rate', 'occ_momentum', 'occ_trend',
    'occ_rolling_mean_3', 'occ_yoy', 'std_proportion', 'is_increasing',
    'is_peak_season', 'js_proportion', 'fmy_proportion', 'spr_proportion',
    'occ_rolling_mean_6', 'occ_rolling_std_3', 'occ_lag_1',
]

# Best hyperparams from notebook hyperparameter search
SINGLE_BEST = {'batch': 4, 'lstm1': 32, 'lstm2': 16, 'dropout': 0.3}
MULTI_BEST  = {'batch': 4, 'lstm1': 32, 'lstm2': 8,  'dropout': 0.2}


# ─────────────────────────────────────────────────────────────────────────────
# Public entry point
# ─────────────────────────────────────────────────────────────────────────────

def train_lstm_model(training_data: list, model_type: str, output_path: str) -> dict:
    try:
        import tensorflow as tf
        from tensorflow import keras
        from sklearn.preprocessing import MinMaxScaler

        if not training_data:
            return {'success': False, 'error': 'No training data provided'}

        # ── Step 1: Aggregate raw records → one row per month ─────────────────
        monthly_df = _build_monthly_df(training_data)
        if monthly_df is None or len(monthly_df) < SEQ_LEN + 4:
            n = len(monthly_df) if monthly_df is not None else 0
            return {'success': False,
                    'error': f'Not enough monthly data: {n} months (need ≥ {SEQ_LEN + 4})'}

        print(f"[train] months in dataset: {len(monthly_df)} "
              f"({monthly_df['month'].iloc[0]} → {monthly_df['month'].iloc[-1]})")

        # ── Step 2: Engineer ALL 30 features (mirrors Data_Hotel.ipynb) ───────
        monthly_df = _engineer_all_features(monthly_df)

        # ── Step 3: Build combined matrix [30 features | 4 targets] ──────────
        all_cols    = ALL_FEATURES_30 + TARGETS
        raw_matrix  = monthly_df[all_cols].values.astype(np.float64)

        # ── Step 4: Fit MinMaxScaler on the FULL matrix (same as notebook) ────
        # Notebook: scaler.fit_transform on all 58 rows × 34 cols at once.
        # For retraining: fit on full available dataset so normalization adapts.
        scaler       = MinMaxScaler()
        scaled_full  = scaler.fit_transform(raw_matrix)

        # ── Step 5: Extract 15-feature X and target y from scaled matrix ──────
        col_index   = {c: i for i, c in enumerate(all_cols)}
        feat_idx    = [col_index[f] for f in FEATURES_15]
        target_idx  = [col_index[t] for t in TARGETS]

        X_all = scaled_full[:, feat_idx]                        # (n, 15)
        if model_type == 'single':
            # Single output: Kamar_Terjual (index 0 in FEATURES_15)
            kt_idx = col_index['Kamar_Terjual']
            y_all  = scaled_full[:, kt_idx:kt_idx+1]           # (n, 1)
        else:
            y_all  = scaled_full[:, target_idx]                 # (n, 4)

        n = len(X_all)

        # ── Step 6: Chronological 80-10-10 split ─────────────────────────────
        train_end = int(n * 0.80)
        val_end   = int(n * 0.90)

        X_train = X_all[:train_end];          y_train = y_all[:train_end]
        X_val   = X_all[train_end:val_end];   y_val   = y_all[train_end:val_end]
        X_test  = X_all[val_end:];            y_test  = y_all[val_end:]

        # ── Step 7: Create LSTM sequences ────────────────────────────────────
        X_train_seq, y_train_seq = _create_sequences(X_train, y_train)
        X_val_seq,   y_val_seq   = _create_with_borrow(X_val,   y_val,   X_train)
        X_test_seq,  y_test_seq  = _create_with_borrow(
            X_test, y_test, np.vstack([X_train, X_val])
        )

        if len(X_train_seq) < 4:
            return {'success': False,
                    'error': f'Too few training sequences: {len(X_train_seq)}'}

        print(f"[train] sequences — train:{len(X_train_seq)} "
              f"val:{len(X_val_seq)} test:{len(X_test_seq)}")

        # ── Step 8: Build model ───────────────────────────────────────────────
        tf.random.set_seed(42)
        np.random.seed(42)

        p = SINGLE_BEST if model_type == 'single' else MULTI_BEST
        output_units = 1 if model_type == 'single' else 4
        model = _build_model(p['lstm1'], p['lstm2'], p['dropout'], output_units)

        print(f"[train] hyperparams: batch={p['batch']} lstm1={p['lstm1']} "
              f"lstm2={p['lstm2']} dropout={p['dropout']}")

        # ── Step 9: Train ─────────────────────────────────────────────────────
        os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
        best_ckpt = output_path + '.ckpt.keras'

        callbacks = [
            keras.callbacks.ModelCheckpoint(
                best_ckpt, monitor='val_loss',
                save_best_only=True, mode='min', verbose=0
            ),
            keras.callbacks.EarlyStopping(
                monitor='val_loss', patience=30,
                restore_best_weights=True, verbose=1
            ),
            keras.callbacks.ReduceLROnPlateau(
                monitor='val_loss', factor=0.5,
                patience=15, min_lr=1e-6, verbose=0
            ),
        ]

        history = model.fit(
            X_train_seq, y_train_seq,
            validation_data=(X_val_seq, y_val_seq),
            epochs=200,
            batch_size=p['batch'],
            callbacks=callbacks,
            verbose=1,
        )

        # ── Step 10: Load best checkpoint and evaluate ────────────────────────
        best_model = keras.models.load_model(best_ckpt)
        metrics    = _evaluate(best_model, X_test_seq, y_test_seq)

        print(f"[train] DONE — epochs:{len(history.history['loss'])} "
              f"MAPE:{metrics['mape']}% R²:{metrics['r2_score']}")

        # ── Step 11: Save final model ─────────────────────────────────────────
        best_model.save(output_path)
        try:
            os.remove(best_ckpt)
        except Exception:
            pass

        return {
            'success':           True,
            'metrics':           metrics,
            'training_samples':  len(X_train_seq),
            'validation_samples': len(X_val_seq),
            'test_samples':      len(X_test_seq),
            'epochs_trained':    len(history.history['loss']),
            'final_train_loss':  float(history.history['loss'][-1]),
            'final_val_loss':    float(history.history['val_loss'][-1]),
        }

    except Exception as e:
        import traceback
        traceback.print_exc()
        return {'success': False, 'error': str(e), 'error_type': type(e).__name__}


# ─────────────────────────────────────────────────────────────────────────────
# Step 1: Aggregate raw per-(month, room_type) records → one row per month
# Mirrors Data_Hotel.ipynb cells 13-15 (daily pivot → monthly aggregation)
# ─────────────────────────────────────────────────────────────────────────────

def _build_monthly_df(training_data: list):
    import pandas as pd

    by_month = {}
    for rec in training_data:
        m  = rec.get('month', '')
        rt = rec.get('room_type_code', '').upper()
        if not m:
            continue
        by_month.setdefault(m, {})[rt] = rec

    if not by_month:
        return None

    rows = []
    for month in sorted(by_month.keys()):
        recs      = by_month[month]
        std_rooms = float(recs.get('STD', {}).get('total_occupied', 0) or 0)
        spr_rooms = float(recs.get('SPR', {}).get('total_occupied', 0) or 0)
        fmy_rooms = float(recs.get('FMY', {}).get('total_occupied', 0) or 0)
        js_rooms  = float(recs.get('JS',  {}).get('total_occupied', 0) or 0)
        total     = std_rooms + spr_rooms + fmy_rooms + js_rooms
        revenue   = float(sum(r.get('total_revenue', 0) or 0 for r in recs.values()))

        year, mon        = int(month[:4]), int(month[5:7])
        days_in_month    = calendar.monthrange(year, mon)[1]
        available_rn     = float(days_in_month * TOTAL_ROOMS)
        occupancy_rate   = (total / available_rn * 100.0) if available_rn else 0.0

        rows.append({
            'month':                 month,
            'Date':                  pd.Timestamp(f'{month}-01'),
            'Kamar_STD':             std_rooms,
            'Kamar_SPR':             spr_rooms,
            'Kamar_FMY':             fmy_rooms,
            'Kamar_JS':              js_rooms,
            'Kamar_Terjual':         total,
            'Revenue':               revenue,
            'Okupansi_Rate':         occupancy_rate,
            'Available_Room_Nights': available_rn,
        })

    if not rows:
        return None

    return pd.DataFrame(rows).sort_values('month').reset_index(drop=True)


# ─────────────────────────────────────────────────────────────────────────────
# Step 2: Engineer ALL 30 features — mirrors Data_Hotel.ipynb cells 26-30
# ─────────────────────────────────────────────────────────────────────────────

def _engineer_all_features(df):
    import pandas as pd

    occ = df['Okupansi_Rate']
    rev = df['Revenue']
    kt  = df['Kamar_Terjual']

    # ── Temporal (cell 26) ────────────────────────────────────────────────────
    df['month_sin']      = np.sin(2 * np.pi * df['Date'].dt.month / 12)
    df['month_cos']      = np.cos(2 * np.pi * df['Date'].dt.month / 12)
    df['quarter']        = (df['Date'].dt.quarter - 1) / 3.0   # normalize 0-1 like notebook
    df['is_peak_season'] = df['Date'].dt.month.isin([6, 7, 12]).astype(float)
    df['is_low_season']  = df['Date'].dt.month.isin([2, 3, 9]).astype(float)
    df['time_index']     = range(len(df))

    # ── Lag features (cell 27) ────────────────────────────────────────────────
    df['occ_lag_1']      = occ.shift(1)
    df['occ_lag_3']      = occ.shift(3)
    df['occ_lag_6']      = occ.shift(6)
    df['occ_lag_12']     = occ.shift(12)
    df['revenue_lag_1']  = rev.shift(1)
    df['revenue_lag_3']  = rev.shift(3)

    # ── Rolling statistics (cell 28) ─────────────────────────────────────────
    df['occ_rolling_mean_3'] = occ.rolling(3).mean()
    df['occ_rolling_mean_6'] = occ.rolling(6).mean()
    df['occ_rolling_std_3']  = occ.rolling(3).std()
    df['occ_rolling_std_6']  = occ.rolling(6).std()
    df['occ_trend']          = occ.diff()
    df['occ_momentum']       = occ.diff().diff()
    df['is_increasing']      = (df['occ_trend'] > 0).astype(float)

    # ── Business metrics (cell 29) ────────────────────────────────────────────
    df['available_room_nights'] = df['Available_Room_Nights']
    safe_kt                     = kt.replace(0, np.nan)
    df['adr']                   = rev / safe_kt
    df['revpar']                = rev / df['Available_Room_Nights']
    df['std_proportion']        = df['Kamar_STD'] / safe_kt
    df['spr_proportion']        = df['Kamar_SPR'] / safe_kt
    df['fmy_proportion']        = df['Kamar_FMY'] / safe_kt
    df['js_proportion']         = df['Kamar_JS']  / safe_kt
    df['occ_yoy']               = occ.diff(12)
    df['revenue_yoy']           = rev.diff(12)

    # ── Fill NaN — same as notebook: ffill → bfill (cell 30) ─────────────────
    df = df.ffill().bfill()

    return df


# ─────────────────────────────────────────────────────────────────────────────
# Sequence helpers — exact copies from both LSTM notebooks
# ─────────────────────────────────────────────────────────────────────────────

def _create_sequences(X, y, seq_len=SEQ_LEN):
    Xs, ys = [], []
    for i in range(seq_len, len(X)):
        Xs.append(X[i - seq_len:i])
        ys.append(y[i])
    return np.array(Xs), np.array(ys)


def _create_with_borrow(X_curr, y_curr, X_prev, seq_len=SEQ_LEN):
    if len(X_prev) < seq_len:
        pad    = np.zeros((seq_len - len(X_prev), X_prev.shape[1]))
        X_prev = np.vstack([pad, X_prev])
    X_combined = np.vstack([X_prev[-seq_len:], X_curr])
    Xs, ys = [], []
    for i in range(len(X_curr)):
        Xs.append(X_combined[i:i + seq_len])
        ys.append(y_curr[i])
    return np.array(Xs), np.array(ys)


# ─────────────────────────────────────────────────────────────────────────────
# Model architecture — identical in both LSTM notebooks
# ─────────────────────────────────────────────────────────────────────────────

def _build_model(lstm1, lstm2, dropout, output_units):
    from tensorflow import keras
    from tensorflow.keras import layers

    model = keras.Sequential([
        keras.Input(shape=(SEQ_LEN, len(FEATURES_15))),
        layers.LSTM(lstm1, return_sequences=True),
        layers.Dropout(dropout),
        layers.LSTM(lstm2),
        layers.Dropout(dropout),
        layers.Dense(8, activation='relu'),
        layers.Dense(output_units),
    ])
    model.compile(optimizer=keras.optimizers.Adam(0.001), loss='mse', metrics=['mae'])
    return model


# ─────────────────────────────────────────────────────────────────────────────
# Evaluation — matches notebook's calc_mape / calc_r2
# ─────────────────────────────────────────────────────────────────────────────

def _evaluate(model, X_test, y_test) -> dict:
    from sklearn.metrics import r2_score, mean_squared_error

    y_pred = model.predict(X_test, verbose=0)
    y_pred = np.clip(y_pred, 0, 1)

    eps  = 1e-10
    mape = float(np.mean(
        np.abs((y_test.flatten() - y_pred.flatten()) /
               (np.abs(y_test.flatten()) + eps))
    ) * 100)
    r2   = float(r2_score(y_test.flatten(), y_pred.flatten()))
    rmse = float(np.sqrt(mean_squared_error(y_test.flatten(), y_pred.flatten())))

    return {
        'mape':     round(mape, 2),
        'r2_score': round(r2, 4),
        'rmse':     round(rmse, 4),
    }

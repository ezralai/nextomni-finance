# Data Science & Machine Learning Core Learning Notes
## Personal Finance Intelligence & Spending Forecasting System

> **Audience**: Designed as a clear, rigorous, beginner-to-intermediate conceptual and practical reference for understanding time-series feature engineering, linear regularized models, baseline benchmarks, and anomaly detection.

---

## 1. Data Granularity: Raw Transaction Logs vs. Time-Series Series

In personal finance software, transaction data arrives as **irregular, event-driven records**:
- On Saturday, a user may swipe their card 4 times.
- On Monday and Tuesday, they may make 0 transactions.
- On the 28th of the month, their salary deposit arrives.

### Why We Aggregate Before Forecasting
Machine learning regressors (such as Linear Regression or Ridge Regression) require a **regular grid** (fixed time intervals like Daily, Weekly, or Monthly) to compute lag features and forecast consistent steps into the future.

$$\text{Monthly Total } Y_t = \sum_{i \in \text{Month } t} \text{Amount}_i \quad \text{for all } \text{Type} = \text{'expense'}$$

- **Daily Grid**: Extremely noisy in personal finance (many zeroes on non-spending days, high variance).
- **Monthly Grid**: Smooths out day-to-day noise and matches standard financial cycles (monthly salaries, rent, utility bills, subscriptions).

---

## 2. The Danger of Time-Series Data Leakage

In traditional tabular machine learning (e.g., predicting customer churn or image classification), we often perform a **random train/test split**:
```python
# WARNING: DO NOT DO THIS FOR TIME SERIES!
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, shuffle=True)
```

### Why Random Shuffling Breaks Time Series
If you randomly shuffle time series data:
1. The model is trained on data from **October 2025** to predict **May 2025**.
2. Future information (future economic trends, holidays, salary increases) leaks backward into the training set.
3. The model achieves deceptively low test errors during evaluation, but catastrophically fails when deployed to predict actual future months.

### The Solution: Strict Chronological Split
Always split data using a temporal boundary:
```
[── Training Window (Jan 2025 - Jan 2026) ──] ──> [── Holdout Test (Feb 2026 - Jul 2026) ──]
```
The model is fitted **only** on the past, and evaluated on unseen future periods.

---

## 3. Lag Features: How Time Remembers

A **Lag Feature** represents the historical value of a time series shifted forward by $k$ time steps:

$$\text{Lag}_k(t) = Y_{t - k}$$

- $\text{Lag}_1$: Spending in the immediately preceding month ($Y_{t-1}$).
- $\text{Lag}_2$: Spending 2 months ago ($Y_{t-2}$).
- $\text{Lag}_3$: Spending 3 months ago ($Y_{t-3}$).

### In Pandas:
```python
df['lag_1'] = df['total_expense'].shift(1)
df['lag_2'] = df['total_expense'].shift(2)
```

### Warmup Period & NaN Handling
Because $\text{Lag}_3$ requires 3 previous months of data, the first 3 rows of your dataset will contain `NaN` (Not a Number) values. These rows form the **warmup period** and must be dropped before training:
```python
modeling_df = df.dropna().copy()
```

---

## 4. Rolling Windows & Moving Averages

A **Rolling Window** computes a statistical summary (such as mean, median, or standard deviation) over a fixed historical window of size $w$:

$$\text{Rolling Mean}_w(t) = \frac{1}{w} \sum_{i=1}^{w} Y_{t - i}$$

### Important Leakage Rule:
To predict month $t$, the rolling average must **not** include month $t$ itself! It must only average completed historical months ($Y_{t-1}, Y_{t-2}, \dots$).
```python
# Correct (zero leakage): shift(1) first, then compute rolling mean
df['rolling_3m_mean'] = df['total_expense'].shift(1).rolling(window=3).mean()
```

---

## 5. Degrees of Freedom & Overfitting in Small Time Series

One of the most valuable insights in real-world personal finance data science is understanding **sample size limitations**:

### The Problem:
- A personal finance user might only have 18 months of transaction history ($N = 18$).
- After losing 3 months to lag feature warmup, you have $N = 15$ rows.
- If you split chronologically (e.g. 9 train, 6 test), your training set has only **9 observations**.

### Mathematical Consequence:
In Ordinary Least Squares (OLS) regression:
$$\text{Degrees of Freedom} = N_{\text{train}} - P - 1$$
Where $N_{\text{train}}$ is the number of training samples, and $P$ is the number of features.
- If you use 5 features (`lag_1`, `lag_2`, `lag_3`, `rolling_3m_mean`, `days_in_month`):
$$\text{Degrees of Freedom} = 9 - 5 - 1 = 3$$
With only 3 degrees of freedom, the linear model overfits the training noise and yields wildly unstable weights, leading to poor test performance.

### The Solution:
1. **Regularization (Ridge Regression)**: Penalizes large coefficient weights with an L2 penalty ($\alpha \sum \beta_j^2$).
2. **Heuristic Baselines**: Simple 3-month moving averages often naturally outperform unregularized ML models on small personal datasets. **Reporting this result honestly demonstrates mature engineering judgment.**

---

## 6. Evaluation Metrics Explained

When evaluating expense forecasts, three primary metrics are used:

### A. Mean Absolute Error (MAE)
$$\text{MAE} = \frac{1}{n} \sum_{i=1}^{n} |y_i - \hat{y}_i|$$
- **Intuition**: On average, how many dollars (or Ringgit) is the forecast off by?
- **Advantage**: Expressed in the exact same monetary units as the expense, easy to interpret by non-technical stakeholders.

### B. Root Mean Squared Error (RMSE)
$$\text{RMSE} = \sqrt{\frac{1}{n} \sum_{i=1}^{n} (y_i - \hat{y}_i)^2}$$
- **Intuition**: Similar to MAE, but squares the errors before taking the root.
- **Advantage**: **Penalizes large outlier misses heavily**. If a model predicts off by $2,000 once, RMSE will penalize it far more than MAE.

### C. Mean Absolute Percentage Error (MAPE)
$$\text{MAPE} = \frac{100\%}{n} \sum_{i=1}^{n} \left| \frac{y_i - \hat{y}_i}{y_i} \right|$$
- **Intuition**: What percentage error does the model make relative to the actual expense?
- **Caution**: If actual spending is very small (near 0), MAPE can explode to astronomical percentages.

---

## 7. Anomaly Detection: Statistical Rules vs. Machine Learning

Anomalies in financial data can represent:
1. An unexpected high-ticket purchase (e.g. laptop or annual car insurance).
2. Subscription billing errors.
3. Potentially suspicious activity.

### A. Statistical Rule: Interquartile Range (IQR) Multiplier
- Calculate the 25th percentile ($Q_1$) and 75th percentile ($Q_3$) of spending in a category.
- Calculate $\text{IQR} = Q_3 - Q_1$.
- Any transaction where $\text{Amount} > Q_3 + (k \times \text{IQR})$ (commonly $k = 2.0$ or $k = 3.0$) is flagged.
- **Why it works**: Robust against skewed financial distributions, requires zero model training, completely transparent to explain.

### B. Machine Learning: Isolation Forest
- An unsupervised ensemble algorithm based on Decision Trees.
- **How it works**: Randomly selects a feature and splits the value.
- Outliers and anomalous transactions require **fewer splits to isolate** from normal clustered transactions.
- **Advantage**: Can consider multidimensional interactions (e.g. transaction amount, day of week, and category history simultaneously).
- **Limitation**: Without true ground-truth fraud labels, precision and recall cannot be mathematically verified. It serves as a prioritization heuristic for human review.

# AI Implementation Context for Website Agents

This document consolidates the AI activities implemented in this workspace and turns them into a practical blueprint for production website agents.

## 1 What Is Already Implemented in This Workspace

### Generative AI activities (LangChain + Mistral + tools)

- Basic LLM invocation with `mistral-large-latest`, prompt messages, and usage metadata.
- Token usage and request cost estimation from model metadata.
- System persona prompting for domain-specific assistants.
- Structured output with Pydantic schemas.
- JSON output parsing and runtime configurable model temperature.
- Action routing/classification using enum-like constrained outputs.
- Task-specific structured workflows:
  - Boolean QA
  - Math step-by-step reasoning with verification fields
  - Tone scoring
  - Translation with source/target language fields
- Text representations:
  - Bag of Words (`CountVectorizer`)
  - Sentence embeddings (`all-MiniLM-L6-v2`) and cosine similarity
- RAG patterns:
  - Simple context-grounded prompting
  - Full PDF -> chunking -> embeddings -> FAISS retrieval -> grounded answer
- Agentic workflows:
  - ReAct agents with external tools (Tavily search, Wikipedia, Arxiv)
  - Prompt templates with strict answer structure

### Predictive AI activities (ML workflows)

- Data ingestion, cleaning, feature engineering, and visualization.
- Preprocessing pipelines (`ColumnTransformer`, `StandardScaler`, `OneHotEncoder`).
- Imbalance handling (`class_weight`, `scale_pos_weight`).
- Classification (Logistic Regression, XGBoost).
- Regression (Linear Regression, Random Forest, XGBoost, objectives including Poisson/Tweedie in notebooks).
- Time-series forecasting (seasonal naive baseline, AR, SARIMA/ARIMA notebook workflows).
- Cross-validation and model comparison metrics (R2, MAE, RMSE, ROC-AUC, F1).
- Dimensionality reduction and clustering tracks in dedicated notebooks.

## 2 Recommended Website AI Agent Architecture

Use a modular architecture with 6 services:

1. `gateway-api`

- Receives user requests, authentication, rate limiting.
- Routes to the right AI capability based on intent.

2. `llm-service`

- Handles prompts, system personas, structured outputs, and model configs.
- Contains your reusable prompt templates and schema definitions.

3. `retrieval-service`

- Document ingestion, chunking, embedding generation, vector search (FAISS or managed vector DB).
- Returns grounded context snippets with metadata.

4. `agent-service`

- ReAct-style orchestration for multi-step tasks and tool usage.
- Tool registry: search, wiki, arxiv, internal APIs.

5. `ml-inference-service`

- Hosts predictive models for classification/regression/forecasting.
- Returns predictions, confidence, and feature contribution metadata.

6. `observability-service`

- Logging, token/cost tracking, latency metrics, quality checks, and offline evaluation.

## 3 Agent Types You Can Deploy Immediately

### A. Transit or domain chatbot agent (persona-driven)

Based on the system prompt approach already used.

Responsibilities:

- Answer FAQ and service questions in target tone.
- Keep responses concise and consistent with brand persona.

Implementation notes:

- Keep persona in a dedicated versioned prompt file.
- Add locale/language controls in prompt variables.

### B. Action router agent

Based on constrained schema routing (`Literal` action output).

Responsibilities:

- Convert natural language input into one of your backend actions.

Suggested action schema:

- `FindRoute`
- `CheckPrice`
- `GetSchedule`
- `Clarify`

Production tip:

- Always return `confidence` and `reason` fields for safer orchestration.

### C. Structured task agents

Based on your `bool`, `math`, `tone`, `translate` files.

Use cases:

- Compliance-safe yes/no decisions.
- Educational step-by-step explainer agent.
- Moderation/tone analysis agent.
- Website localization/translation agent.

Production tip:

- Validate schema server-side and reject malformed model outputs.

### D. RAG knowledge agent

Based on `rag_basique` and `faiss` workflows.

Responsibilities:

- Answer strictly from internal documents.
- Return "I do not know" if evidence is missing.

Pipeline:

1. Load documents
2. Chunk with overlap
3. Embed chunks
4. Index vectors
5. Retrieve top-k
6. Build context prompt
7. Generate grounded answer

Production tip:

- Return citations (document, page, chunk id) with each answer.

### E. Research assistant agent (ReAct + tools)

Based on `tp4_react.py`, `tp4_arxiv.py`, `tp4_wiki.py`.

Responsibilities:

- Decide when to call external tools.
- Summarize findings in strict structure.
- Provide cited outputs.

Production tip:

- Add tool-usage guardrails: max calls, timeout, domain allowlist.

### F. Predictive analytics agent

Based on Predictive AI notebooks and pipelines.

Responsibilities:

- Run pre-trained models for tabular or time-series inputs.
- Explain outputs in plain language for end users.

Production tip:

- Separate model training from inference.
- Version artifacts and support rollback.

## 4 Endpoints to Expose in Your Website Backend

Suggested REST endpoints:

- `POST /api/ai/chat`
  - Persona chat and direct LLM Q/A.

- `POST /api/ai/route`
  - Intent/action classification (structured output).

- `POST /api/ai/translate`
  - Structured translation output.

- `POST /api/ai/tone-score`
  - Tone score + commentary.

- `POST /api/ai/solve-math`
  - Step-by-step + verification array.

- `POST /api/ai/rag/query`
  - Grounded answer + citations + confidence.

- `POST /api/ai/agent/research`
  - ReAct with tool traces.

- `POST /api/ml/predict/:modelName`
  - ML predictions from deployed models.

- `POST /api/ml/forecast/:seriesName`
  - Time-series forecast endpoint.

## 5 Canonical JSON Contracts

Use strict contracts similar to your Pydantic schemas.

### Action routing

```json
{
  "action": "FindRoute",
  "confidence": 0.93,
  "reason": "User asks how to go from A to B"
}
```

### RAG response

```json
{
  "answer": "...",
  "grounded": true,
  "citations": [{ "doc": "policy.pdf", "page": 3, "chunk_id": "p3_c2" }],
  "confidence": 0.81
}
```

### Prediction response

```json
{
  "model": "xgboost_income_v3",
  "prediction": 1,
  "probability": 0.87,
  "top_features": [
    { "name": "education_num", "impact": 0.21 },
    { "name": "capital_gain", "impact": 0.16 }
  ]
}
```

## 6 Prompting and Guardrail Rules for Production

1. Always use system prompts for role, tone, and constraints.
2. Prefer structured output for anything consumed by code.
3. For RAG, force answers to use retrieved context only.
4. Include fallback behavior (`I do not know`) when evidence is weak.
5. Enforce token and latency budgets per endpoint.
6. Sanitize user input and redact secrets before logging.
7. Add content moderation layer before returning responses to users.

## 7 Data and Model Lifecycle

### LLM/RAG lifecycle

- Prompt versioning
- Retrieval evaluation (precision@k, answer grounding)
- Cost monitoring by endpoint

### Predictive model lifecycle

- Offline training and validation
- Artifact versioning (`model`, `preprocessor`, `feature schema`)
- CI checks for drift and performance regression
- Canary deployment before full rollout

## 8 Frontend Integration Pattern

For each AI feature in UI:

1. Frontend calls backend endpoint.
2. Backend validates input schema.
3. AI service runs capability.
4. Backend validates output schema.
5. UI renders result + confidence/citations if available.

Recommended UX signals:

- Loading step indicator (Retrieving context, Thinking, Final answer)
- "Based on sources" badge for RAG
- Confidence indicator for predictions/classification

## 9 Minimum Security Checklist

- Keep API keys server-side only.
- Use per-user rate limiting.
- Add request size limits.
- Add tool allowlist for ReAct agents.
- Store audit logs for tool invocations and model outputs.
- Block prompt-injection attempts in retrieval context with explicit filtering.

## 10 Practical Build Order (Fastest Path)

1. Start with `chat`, `route`, and `rag/query` endpoints.
2. Add `translate`, `tone-score`, and `solve-math` schemas.
3. Add ReAct research endpoint with strict tool budget.
4. Deploy one predictive endpoint (classification or forecast).
5. Add observability dashboards for latency, cost, and quality.

This order lets you ship useful AI quickly while preserving reliability and control.

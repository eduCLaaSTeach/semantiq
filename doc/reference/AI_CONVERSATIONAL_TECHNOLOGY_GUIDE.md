# Semantiq AI and Conversational AI Technology Selection Reference

**Reference ID:** AI-TECH-001  
**Status:** Architecture decision reference  
**Technology review date:** 22 August 2026  
**Applies to:** Any Semantiq phase or feature involving LLMs, AI assistants, Fabric Data Agent, RAG, conversational UI, Copilot Studio, agent orchestration, tool calling, MCP, model hosting, evaluation or autonomous actions.

> **Mandatory decision gate:** Claude Code must read this reference before implementing any AI or conversational-AI capability. It must compare the Microsoft-first and open-source options for the exact scenario, verify current product/API maturity and regional availability, record the decision in `doc/execution/AI-TECHNOLOGY-DECISION.md`, present it to the user, and wait for approval before material implementation.

> **Mandatory data-boundary gate:** Before an AI technology decision, also read `DATA_PROTECTION_SOVEREIGNTY_STANDARD.md` and the current `doc/context/DATA_SOVEREIGNTY_REGISTER.md`. The decision must identify model/runtime region, prompt/grounding data, embeddings/vector storage, conversation-history storage/retention, telemetry location and any cross-geo setting or exception. Cross-geo processing/storage/history remains OFF unless explicitly approved.

## SemantIQ Application Stack Constraint

The confirmed primary application is Laravel 13/PHP 8.5 with React 19 and MySQL on cPanel. Prefer integrations that can be called through supported HTTP/REST/SDK boundaries without adding a second application runtime.

If the best AI framework or model-serving option requires .NET, Python, Node.js, GPU infrastructure or a separate container/service, treat it as a sidecar/service architecture change. Document hosting, authentication, network boundary, data residency, operations, cost and failure mode in `doc/execution/AI-TECHNOLOGY-DECISION.md` and obtain explicit user approval before adding that runtime.

Fabric Data Agent and Copilot Studio should be evaluated first when they satisfy the governed Fabric/conversational use case because they can reduce custom AI-runtime code inside the Laravel product.


## 1. Semantiq default architecture principle

Semantiq should **not use an LLM to perform deterministic Fabric provisioning or data-engineering operations directly**. Fabric setup, permissions, workspace creation, pipelines, deployment and configuration remain deterministic API/workflow operations with audit evidence and explicit approval gates. AI may recommend configuration, generate drafts, explain failures or propose mappings, but execution must use validated adapters and policy-controlled actions.

For conversational intelligence, use the smallest architecture that satisfies the requirement. Start with a single governed agent or tool-calling flow. Introduce multi-agent orchestration only when there are genuinely separate domains, tools, permissions or lifecycle boundaries that justify it.

The application architecture must keep these concerns replaceable behind interfaces: **Model Provider**, **Agent Runtime**, **Knowledge/Retrieval Provider**, **Tool/MCP Provider**, **Conversation Store**, **Evaluation/Observability Provider**, **Safety/Policy Provider** and **Channel/UI Adapter**.

## 2. Recommended Microsoft-first stack

| Requirement / scenario | Preferred Microsoft technology | Why it fits Semantiq | Important notes |
| --- | --- | --- | --- |
| Conversational analytics over governed Fabric data | **Microsoft Fabric Data Agent / Fabric IQ** | Native fit for Lakehouse, Warehouse, semantic models and other supported Fabric sources; keeps analytical answers grounded in governed Fabric data. | Default for structured business-data Q&A. Preserve RLS/OLS and user identity. |
| Low-code enterprise conversational app, Teams/M365/web channels | **Microsoft Copilot Studio** | Generative orchestration can select tools, knowledge and connected agents; strong Microsoft channel and Power Platform integration. | Best when customer wants low-code operations and Microsoft 365 channels. Keep complex data analytics grounded in Fabric Data Agent rather than duplicating business logic in prompts. |
| Custom-coded production agent sidecar/service in .NET or Python | **Microsoft Agent Framework (MAF)** | Production-grade open framework for agents and multi-agent workflows, with workflows, checkpointing, human-in-the-loop, MCP and provider flexibility. | For new Microsoft-centric coded agent work, evaluate MAF before starting new Semantic Kernel or AutoGen implementations. In SemantIQ this would be a separately approved sidecar/service because the primary runtime is Laravel/PHP. |
| Managed hosting, model access, tracing, evaluation and enterprise agent lifecycle | **Microsoft Foundry Agent Service** | Managed prompt/hosted agents, model catalog, identity, scaling, observability and evaluation. | Pair with MAF when Semantiq needs a custom agent beyond Fabric Data Agent/Copilot Studio. |
| Enterprise RAG over unstructured documents/knowledge | **Microsoft Foundry IQ / Azure AI Search agentic retrieval** | Managed knowledge bases, hybrid/vector retrieval, query planning, citations and enterprise data integrations. | Use when the requirement is document/knowledge retrieval, not as a replacement for Fabric Data Agent's structured analytical role. Verify which Foundry IQ capabilities are GA vs preview at implementation time. |
| Custom web/mobile conversational UI with streaming and human approvals | **Agent Framework + AG-UI** (when compatible with the approved stack) | Standard agent-interface protocol supports SSE streaming, session state, tool events and human-in-the-loop interactions. | Optional. Do not introduce AG-UI if the existing application stack already has a simpler proven conversation transport. |
| Tool interoperability | **MCP with Copilot Studio, MAF or Foundry** | Standardized tool exposure reduces custom connector coupling. | Only use vetted, allowlisted MCP servers. Require least privilege, input validation, approval for high-impact actions and full audit logs. |

### Microsoft recommendation for Semantiq

For the current product baseline, the default order of evaluation is:

1. **Fabric Data Agent** for structured, governed business-data conversations.
2. **Semantiq custom conversational UI** calling the governed backend service/Data Agent.
3. **Copilot Studio** when Teams/Microsoft 365/low-code orchestration is a customer requirement.
4. **Microsoft Agent Framework + Microsoft Foundry Agent Service** when a custom coded agent must reason across tools, knowledge sources or long-running workflows beyond the Fabric Data Agent scope.
5. **Foundry IQ / Azure AI Search** when unstructured enterprise documents or reusable RAG knowledge bases are required.

This is a decision order, not a requirement to deploy all components.

## 3. Recommended open-source alternatives

| Requirement / scenario | Open-source option | Recommended use | Semantiq guidance |
| --- | --- | --- | --- |
| Stateful/durable custom agent orchestration | **LangGraph** | Long-running stateful workflows, persistence, streaming, human-in-the-loop and detailed orchestration control. | Strong alternative when provider independence or non-Microsoft hosting is required. Keep Fabric connectivity behind Semantiq adapters. |
| Document-centric RAG, indexing and data-aware agent workflows | **LlamaIndex** | Retrieval pipelines, document indexing, query engines, structured outputs and agent workflows. | Consider when unstructured knowledge is central and Foundry IQ/Azure AI Search is not selected. |
| Self-hosted high-throughput LLM serving on Linux/GPU | **vLLM** | Production-oriented OpenAI-compatible model serving for supported open models. | Preferred self-hosted inference option when scale and throughput matter. Place behind enterprise auth/reverse proxy; do not rely on a model server's basic API key alone for perimeter security. |
| Local development and small POC model serving | **Ollama** | Fast local developer setup and tool-calling experiments. | Good developer/POC option. Do not make it the default multi-tenant production serving layer without a separate production architecture review. |
| Open-source conversational orchestration with provider flexibility | **LangGraph + vLLM** | End-to-end self-hosted agent runtime and model serving. | Use when customers explicitly require non-Azure/self-hosted AI. Add enterprise identity, secret management, audit, moderation/safety, rate limiting and observability. |

## 4. Scenario decision matrix

| Semantiq scenario | Default choice | Alternative | Decision notes |
| --- | --- | --- | --- |
| Ask revenue/attendance/pipeline questions over Fabric semantic data | Fabric Data Agent | Custom MAF/LangGraph agent calling governed Fabric APIs | Do not rebuild semantic calculations in prompts. |
| Teams chatbot for governed Fabric intelligence | Copilot Studio + Fabric Data Agent | Custom Teams/web integration + MAF | Prefer Copilot Studio when low-code operations and Microsoft channels are primary. |
| Semantiq's own web conversational experience | Existing Semantiq frontend + backend conversation API + Fabric Data Agent | MAF/Foundry or LangGraph if orchestration is required | Keep frontend independent from model/provider. Stream responses where useful. |
| Cross-domain agent using Fabric data plus document knowledge plus actions | MAF + Foundry Agent Service + Fabric Data Agent + Foundry IQ/tools | LangGraph + Fabric adapters + open-source RAG | Human approval required for state-changing actions. |
| Enterprise document assistant | Foundry IQ/Azure AI Search + Foundry/MAF | LlamaIndex + chosen vector store | Preserve citations and source permissions. |
| Fully self-hosted AI requirement | MAF or LangGraph + vLLM | Ollama for dev/POC | Confirm GPU sizing, model license, data residency, safety and operations. |
| Multi-agent workflow | MAF Workflows | LangGraph | Only use multi-agent when simpler single-agent/tool patterns are insufficient. |

## 5. Technology choices that must not be hard-coded prematurely

Claude Code must not hard-code a particular LLM model, model vendor, vector database or agent framework merely because it appears in an example. The phase plan must evaluate the real requirement against:

- customer data residency and sovereignty;
- Microsoft tenant/region and Fabric capacity constraints;
- GA vs preview status;
- required channels (Semantiq web, Teams, M365, API, mobile);
- structured analytics vs unstructured RAG;
- read-only insight vs action-taking agent;
- latency and concurrency;
- cost and token/compute consumption;
- model/tool permissions and least privilege;
- evaluation quality and ground-truth score;
- observability and supportability;
- open-source model license and commercial-use rights where applicable;
- lock-in tolerance and portability requirements.

## 6. Required AI architecture controls

Any selected AI/conversational technology must meet these controls:

1. **Governed grounding:** AI may only use approved semantic objects, approved Fabric sources or approved knowledge bases.
2. **No direct raw-data bypass:** conversational AI must not bypass Bronze/Silver/Gold, semantic governance or security controls merely to improve an answer.
3. **Identity propagation:** preserve user context where required so source permissions/RLS/OLS remain enforceable.
4. **Human-in-the-loop:** destructive, write-back, financial, permission-changing or production-impacting actions require explicit approval unless a separately approved autonomous policy exists.
5. **Prompt/tool-injection defence:** tools and MCP servers are allowlisted, inputs validated and untrusted content treated as data, not instructions.
6. **Structured tool contracts:** use typed/validated schemas for tool inputs/outputs.
7. **Evaluation before publish:** ground-truth, security, regression and unsupported-question tests must pass.
8. **Observability:** record agent/runtime/model version, prompt/instruction version, tool calls, latency, errors, evaluation results and correlation IDs without leaking sensitive data.
9. **Versioning:** model/runtime/instructions/knowledge configuration are versioned and changes can trigger `Revalidation Required`.
10. **Fallback:** define behavior for model outage, quota/rate limit, unsupported question, knowledge-source failure and agent timeout.

## 7. Claude Code technology-decision workflow

Before material AI implementation, Claude Code must:

1. Read this file and the current phase reference.
2. Inspect the existing repository and approved cloud/hosting constraints.
3. Identify the exact AI scenario and whether it is structured analytics, RAG, tool/action orchestration or conversational presentation.
4. Compare at least one Microsoft-first option and one open-source option when a meaningful open-source equivalent exists.
5. Verify current official documentation, API version, GA/preview status, regional availability, authentication method and licensing/pricing constraints.
6. Create/update `doc/execution/AI-TECHNOLOGY-DECISION.md` using the template in `doc/templates/AI_TECHNOLOGY_DECISION_TEMPLATE.md`.
7. Recommend one option with reasons, trade-offs and rollback/portability approach.
8. Present the decision to the user and wait for explicit approval.
9. Implement behind approved abstractions; do not leak provider-specific assumptions into the entire product.
10. Include the selected stack and versions in the phase verification report.

## 8. Source references for re-verification

Use these as starting points and re-check them at implementation time:

- Microsoft Fabric Data Agent: https://learn.microsoft.com/en-us/fabric/data-science/how-to-create-data-agent
- Microsoft Copilot Studio: https://learn.microsoft.com/en-us/microsoft-copilot-studio/
- Copilot Studio generative orchestration guidance: https://learn.microsoft.com/en-us/microsoft-copilot-studio/guidance/generative-orchestration
- Microsoft Agent Framework: https://learn.microsoft.com/en-us/agent-framework/overview/
- Microsoft Agent Framework GitHub: https://github.com/microsoft/agent-framework
- Microsoft Foundry: https://learn.microsoft.com/en-us/azure/foundry/
- Microsoft Foundry Agent Service: https://learn.microsoft.com/en-us/azure/ai-foundry/agents/overview
- Foundry IQ: https://learn.microsoft.com/en-us/azure/ai-foundry/agents/concepts/what-is-foundry-iq
- AG-UI with Microsoft Agent Framework: https://learn.microsoft.com/en-us/agent-framework/integrations/ag-ui/
- LangGraph: https://docs.langchain.com/oss/python/langgraph/overview
- LlamaIndex: https://docs.llamaindex.ai/
- vLLM: https://docs.vllm.ai/en/latest/serving/online_serving/openai_compatible_server/
- Ollama: https://docs.ollama.com/

> **Freshness rule:** These references reflect the architecture review on 22 August 2026. Claude Code must re-verify the current documentation and release status immediately before implementation because AI products, APIs and licensing change rapidly.

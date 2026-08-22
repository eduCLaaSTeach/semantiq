# AI Technology Decision Record

**Decision ID:** AI-ADR-XXX  
**Phase:** PHASE-XX  
**Status:** Proposed / Approved / Rejected / Superseded  
**Date:** YYYY-MM-DD

## 1. Scenario
Describe the exact user/business capability. State whether this is structured analytics, unstructured RAG, conversational presentation, tool/action orchestration, multi-agent workflow, model hosting, voice, or another AI capability.

## 2. Constraints
- Existing application stack:
- Customer tenant/region/data residency:
- Required channels:
- Required identity/security model:
- Expected concurrency/latency:
- Budget/licensing constraints:
- GA-only requirement or preview allowed:
- Self-hosted/open-source requirement:

## 3. Microsoft-first option
- Technology:
- Architecture:
- APIs/SDKs:
- Authentication/permissions:
- GA/preview status and region availability:
- Advantages:
- Risks/limitations:
- Estimated operational impact:

## 4. Open-source option
- Technology:
- Architecture:
- Model serving/runtime:
- Authentication/security additions required:
- License/commercial-use review:
- Advantages:
- Risks/limitations:
- Estimated operational impact:

## 5. Evaluation
Compare data governance, security, quality, latency, cost, scalability, operability, portability, vendor lock-in and developer skill requirements.

## 6. Recommended decision
State the recommended stack and why it is the best fit for this Semantiq capability.

## 7. Architecture boundaries
List the interfaces/adapters that will isolate the selected provider/runtime from the rest of Semantiq.

## 8. Safety and human-approval controls
Describe grounding, identity propagation, RLS/OLS, tool allowlists, write-action approvals, prompt-injection controls and audit requirements.

## 9. Verification plan
Define ground-truth tests, security tests, failure/fallback tests, performance tests and observability evidence.

## 10. Rollback / alternative path
Explain how the capability can be disabled, replaced or moved to the alternative technology without corrupting customer data or phase status.

## 11. User approval
**User approval required before implementation:** Yes  
**Approval evidence:**

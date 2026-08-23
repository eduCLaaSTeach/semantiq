# Semantiq In-App Help Topic Index

> Each help topic must include prerequisites, required role, portal/app path, copyable values, steps, verification/Re-check, troubleshooting, preview/high-privilege labels and last-reviewed Microsoft reference.

| Topic ID | Help topic |
| --- | --- |
| HLP-SSO-001 | Set up Semantiq SSO and grant tenant admin consent |
| HLP-AUTH-002 | Create the Fabric Automation App Registration |
| HLP-AUTH-003 | Create a certificate or client secret and connect it to Semantiq |
| HLP-FAB-001 | Run the Fabric Readiness Assessment |
| HLP-FAB-002 | Enable required Fabric service-principal tenant settings |
| HLP-FAB-003 | Select or create a Fabric capacity |
| HLP-FAB-004 | Create DEV, TEST and PROD workspaces |
| HLP-FAB-005 | Grant the Semantiq service principal workspace access |
| HLP-SRC-002 | Create and test a Fabric connection |
| HLP-GWY-001 | Configure an on-premises or VNet gateway |
| HLP-ING-001 | Create an ingestion plan and schedule |
| HLP-LKH-001 | Create Lakehouse and Bronze/Silver/Gold layout |
| HLP-DQ-001 | Review and approve data-quality rules |
| HLP-SEM-001 | Review the generated semantic model |
| HLP-SEC-001 | Configure and test RLS/OLS |
| HLP-AI-001 | Prepare approved data and business instructions for AI |
| HLP-AGT-001 | Create, configure, validate and publish a Fabric Data Agent |
| HLP-DEP-001 | Create deployment pipeline and promote DEV -> TEST -> PROD |
| HLP-OPS-001 | Troubleshoot failed Fabric API or job runs |

## AI technology selection help/reference

| ID | Title | Purpose |
| --- | --- | --- |
| HLP-AI-TECH-001 | Select the AI and Conversational AI technology stack | Guides administrators/developers to compare Fabric Data Agent, Copilot Studio, Microsoft Agent Framework/Foundry, Foundry IQ and approved open-source alternatives before enabling an AI capability. See `AI_CONVERSATIONAL_TECHNOLOGY_GUIDE.md`. |


| HLP-SOV-001 | Define customer data residency and processing boundaries | 01/02 | Organisation Setup / Fabric Readiness | Data Protection Admin | Define approved geographies, default-deny cross-geo and exception workflow |
| HLP-SOV-002 | Verify Fabric capacity/workspace region and Multi-Geo placement | 02 | Fabric Readiness / Workspaces | Fabric Admin | Compare actual placement with approved geography |
| HLP-NET-003 | Configure Fabric Private Link / block public access | 02/03 | Security & Networking | Fabric/Azure Admin | Tenant/workspace private connectivity and validation |
| HLP-ENC-004 | Configure Fabric workspace customer-managed key | 02/06 | Workspace Security | Fabric/Azure Security Admin | Key Vault/HSM prerequisites, apply, monitor, rotate/revoke |
| HLP-GOV-005 | Configure Purview sensitivity labels and DLP for Fabric | 06 | Governance | Compliance/Data Protection Admin | Label, DLP/protection policy and verification |
| HLP-AI-SOV-006 | Review Fabric Data Agent/Copilot cross-geo settings | 07 | AI Readiness | Fabric/Data Protection Admin | Processing/storage/conversation-history boundary decision |
| HLP-CTX-007 | Maintain code/data/validation/configuration context | 00-09 | Help Centre | Developer/Admin | How context registers are updated and verified |

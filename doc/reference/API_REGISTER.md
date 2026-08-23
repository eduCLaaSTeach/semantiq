# Semantiq API Register

> Baseline from SRS v0.3 repository-aligned baseline. Revalidate current Microsoft documentation at implementation time before coding.

| ID | Operation | Pattern | Mode | Use in Semantiq |
| --- | --- | --- | --- | --- |
| API-001 | List capacities | GET /v1/capacities | AUTO / read | Discover accessible capacity, SKU, region and state. |
| API-002 | Create workspace | POST /v1/workspaces | AUTO / approval | Create DEV/TEST/PROD workspace. |
| API-003 | Assign workspace to capacity | POST /v1/workspaces/{workspaceId}/assignToCapacity | AUTO / approval | Bind workspace to selected capacity. |
| API-004 | Workspace role assignment | POST /v1/workspaces/{workspaceId}/roleAssignments | AUTO / approval | Grant service principal/user/group role. |
| API-005 | List tenant settings | GET /v1/admin/tenantsettings | AUTO / read | Read effective tenant settings when authorised. |
| API-006 | Update tenant setting | POST /v1/admin/tenantsettings/{tenantSettingName}/update | PREVIEW / feature-flag | Preview; default product behaviour is guided manual + re-check. |
| API-007 | Create connection | POST /v1/connections | AUTO / approval | Create cloud/on-prem/VNet Fabric connection. |
| API-008 | Create gateway | POST /v1/gateways | AUTO where supported | VNet/streaming VNet gateway; on-prem software install still guided. |
| API-009 | Create Lakehouse | POST /v1/workspaces/{workspaceId}/lakehouses | AUTO | Provision Lakehouse. |
| API-010 | Create Data Pipeline | POST /v1/workspaces/{workspaceId}/dataPipelines | AUTO | Create pipeline item; deploy definition as supported. |
| API-011 | Run item job | POST /v1/workspaces/{workspaceId}/items/{itemId}/jobs/{jobType}/instances | AUTO | Run on demand; honour Retry-After. |
| API-012 | Create item schedule | POST /v1/workspaces/{workspaceId}/items/{itemId}/jobs/{jobType}/schedules | AUTO | Create supported schedule. |
| API-013 | Create semantic model | POST /v1/workspaces/{workspaceId}/semanticModels | AUTO | Requires definition; version before deployment. |
| API-014 | Create Data Agent | POST /v1/workspaces/{workspaceId}/dataAgents | AUTO | Create Data Agent; supports LRO. |
| API-015 | Get Data Agent definition | POST .../dataAgents/{dataAgentId}/getDefinition | AUTO | Backup/synchronise definition. |
| API-016 | Update Data Agent definition | POST .../dataAgents/{dataAgentId}/updateDefinition | AUTO / approval | Deploy approved public definition. |
| API-017 | Publish Data Agent | Data Agent publish endpoint | AUTO / release approval | Publish staging configuration after validation. |
| API-018 | Create deployment pipeline | POST /v1/deploymentPipelines | AUTO / approval | Create DEV/TEST/PROD stages. |
| API-019 | Deployment pipeline stage operations | Deployment pipeline APIs | AUTO / approval | Assign workspaces and deploy stage content. |

## Security, sovereignty and configuration automation note

Before automating any region, networking, encryption, tenant-setting, Purview/DLP or AI configuration, verify that a supported current API exists and that the configured identity is supported. If a control is portal-only, preview, unsupported for service principals or requires a separate Azure/Purview role, implement it as a guided/manual approval step rather than bypassing the control.

Every external configuration operation must capture request/correlation IDs where available, target tenant/workspace/resource, desired vs observed configuration, and redacted evidence. Never record secrets or customer data payloads in this register or logs.

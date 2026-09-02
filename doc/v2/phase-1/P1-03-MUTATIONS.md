# P1-03 — the mutations

`CLAUDE.md` §2: *a test that cannot fail is worse than no test.* Every guard
below was broken deliberately, the suite run, and the failure observed. The
mutation is recorded beside the case it belongs to.

**70 mutations. 70 caught.** Four needed a second attempt, and what each exposed
is in `P1-03-USERS-GROUPS-VERIFICATION.md` §3 — three were weaknesses in the
tests, not in the code.

Each mutation was applied to a clean tree, the named tests run, and the tree
restored before the next.

---

## Boundary — the ones that matter most

| ID | Case | Mutation applied | Caught by |
| --- | --- | --- | --- |
| M-N1 | N1 | Removed `RequireSystemAdministrator` from the People route group | `test_every_people_route_refuses_an_authenticated_non_administrator`, `test_a_newly_provisioned_user_can_reach_nothing` |
| M-N2 | N2 | `provision()` writes `platform_role = SystemAdministrator` | `test_a_newly_provisioned_user_can_reach_nothing`, `test_a_request_carrying_a_platform_role_grants_nothing` |
| M-N4a | N4 | The same, against the source guard | `test_no_people_code_assigns_a_platform_role` |
| M-N4b | N4 | `update()` accepts `platform_role` from the request and writes it | `test_a_request_carrying_a_platform_role_grants_nothing`, `test_no_people_code_assigns_a_platform_role` |
| M-N3 | N3 | `RequireSystemAdministrator` imports `GroupMembership` | `test_nothing_outside_people_queries_group_membership` |
| M-N3b-a | N3b | A migration adds `groups.is_admin` | `test_the_group_tables_have_exactly_their_declared_columns`, `test_no_people_column_can_be_read_as_a_grant` |
| M-N3b-b | N3b | A migration adds `groups.owner_role` **and the expected column list is updated to match** — so only the forbidden-word half can catch it | `test_no_people_column_can_be_read_as_a_grant` |
| M-N5 | N5 | `PlatformRole` gains a `GroupOwner` case | `test_the_platform_role_enum_still_has_exactly_one_case` |
| M-N6 | N6 | `IdentityResolver` gains `->orWhere('email', …)` | `test_identity_resolution_never_matches_on_email`, `test_a_sign_in_whose_email_matches_but_whose_subject_does_not_is_refused` |
| M-N40 | N40 | A second `PurgeDependencies` left behind in `Organisation\Support` | `test_the_shared_lifecycle_classes_exist_exactly_once` |

## Provisioning

| ID | Case | Mutation applied | Caught by |
| --- | --- | --- | --- |
| M-N7a | N7 | The service stops refusing first, so the database constraint surfaces to the administrator instead | `test_a_duplicate_identity_is_refused_in_business_language`, `test_object_ids_differing_only_in_case_are_the_same_person` |
| M-N7b | N7, N8 | `users_identity_uq` dropped | `test_the_database_refuses_a_second_identical_identity` |
| M-N9 | N9 | The GUID regex removed from the store request | `test_a_malformed_object_id_is_refused_by_the_server` |
| M-N10 | N10 | `external_subject` and `email` accepted and written by `update()` | `test_the_identity_key_cannot_be_edited_through_any_route` |
| M-N11 | N11 | `provider` and `tenant_id` accepted from the request | `test_provider_and_tenant_come_from_configuration_not_from_the_request` |
| M-N13 | N13 | The Users list renders an empty cell instead of *Not signed in yet* | `test_a_person_who_has_never_signed_in_is_described_in_words` |
| M-N13b | N13 | The record screen does the same | as above |
| M-N14 | N14 | The Object ID label becomes *"Microsoft Entra Object ID (Verified)"* | `test_the_add_user_form_says_the_directory_was_not_checked` |
| M-N14b | N14 | The statement that SemantIQ cannot check the ID is deleted | as above |
| M-prov | D-33 | Both name-and-email notes made identical, so a provisional value reads as a confirmed one | `test_provisional_directory_values_are_labelled_as_provisional` |

## Routing and lifecycle completeness

| ID | Case | Mutation applied | Caught by |
| --- | --- | --- | --- |
| M-N15a | N15 | `Route::get('{user}')` registered beside `users` and `groups` — the collision correction 1 rejected | `test_the_user_and_group_route_sets_are_structurally_disjoint`, `test_no_route_has_a_dynamic_segment_where_another_has_a_static_one`, `test_every_people_route_still_resolves_when_the_route_set_is_reversed` |
| M-N15b | N15 | `whereNumber` removed and a name-shaped route added | `test_a_collection_name_in_a_record_position_is_not_found` |
| M-N16 | N16, N29 | A seventh DELETE route, on membership history | `test_no_route_can_delete_a_membership`, `test_the_application_has_exactly_six_delete_routes` |
| M-N17a | N17 | The organisation-assignment route deleted | `test_every_named_operation_has_a_route` |
| M-N17b | N17 | `assignOrganisation()` renamed away | `test_every_named_operation_is_a_service_method` |
| M-N18 | N18 | A People write returns a bare redirect | `test_every_people_write_confirms_itself`, `test_every_confirmation_is_a_sentence_and_names_nobody`, `test_a_people_write_reaches_the_screen_as_a_confirmation` |

## Lifecycle guards

| ID | Case | Mutation applied | Caught by |
| --- | --- | --- | --- |
| M-N19 | N19 | A dependency guard added to `deactivate()` | `test_a_person_with_every_kind_of_relationship_can_still_be_deactivated` |
| M-N41 | N41 | `refuseIfLastAdministrator()` no longer called — production one click from zero administrators | `test_the_only_active_system_administrator_cannot_be_deactivated` |
| M-N41b | N41b | The guard refuses whenever the target is an administrator | `test_with_two_active_administrators_either_may_be_deactivated` |
| M-N41c-1 | N41c | The check moved **outside** the transaction | `test_the_last_administrator_count_is_a_locking_read_inside_the_transaction` |
| M-N41c-2 | N41c | `lockForUpdate()` dropped from the count | as above |
| M-N20a | N20 | The summary counts historical team memberships too | `test_the_dependency_summary_counts_only_current_relationships` |
| M-N20b | N20 | The zero clause is emitted — *"manages 0 people"* | as above, and `test_changing_organisation_is_permitted_once_nothing_is_current` |
| M-N21 | N21 | Deactivation ends current group memberships | `test_deactivation_changes_no_relationship_row` |
| M-N22 | N22 | Reactivation resurrects ended memberships | `test_reactivation_restores_eligibility_and_nothing_else` |
| M-N23 | N23 | The organisation-change guard disabled | `test_changing_organisation_is_refused_while_current_relationships_exist` |
| M-N24 | N24 | Somebody who has signed in becomes purgeable | `test_somebody_who_has_ever_signed_in_cannot_be_purged`, `test_the_service_refuses_a_purge_directly` |
| M-N25 | N25 | The schema walk replaced by a **current-rows-only** check | `test_ended_membership_history_blocks_a_purge` |
| M-N26 | N26 | `isBootstrapAdministrator()` removed, relying on the schema walk — which cannot see that column | `test_the_bootstrap_administrator_cannot_be_purged` |
| M-N27 | N27 | The purge re-check inside the transaction removed | `test_the_purge_conditions_are_rechecked_inside_the_transaction` |

## Membership rules

| ID | Case | Mutation applied | Caught by |
| --- | --- | --- | --- |
| M-N30 | N30 | The same-organisation check disabled | `test_somebody_from_another_organisation_cannot_join`, `test_no_membership_refusal_exposes_a_database_error` |
| M-N31 | N31 | A user with no organisation is allowed to join | `test_somebody_with_no_organisation_cannot_join` |
| M-N32 | N32 | The current-membership check disabled | `test_a_second_current_membership_is_refused` |
| M-N32b | N32 | `lockForUpdate()` dropped from that read | `test_the_current_membership_read_is_locking_and_inside_the_transaction` |
| M-N33a | N33 | An inactive person may join | `test_an_inactive_person_or_an_inactive_group_cannot_gain_a_member` |
| M-N33b | N33 | An inactive group may gain members | as above |
| M-N42 | N42 | **P1-01's key shape, reproduced faithfully:** `joined_at`/`left_at` cast to DATE **and** `UNIQUE(group_id, user_id, joined_at)` | `test_a_person_may_join_leave_and_rejoin_twice_on_one_day` |
| M-N43 | N43 | The overlap guard removed, so a rejoin may start before the previous period ended | `test_a_rejoin_cannot_start_before_the_previous_period_ended` |
| M-N44 | N44 | The unknown-user refusal removed, so a foreign-key error reaches the administrator | `test_no_membership_refusal_exposes_a_database_error` |
| M-N28 | N28 | The group purge checks only current members | `test_a_group_with_only_ended_memberships_cannot_be_purged` |

## Presentation and privacy

| ID | Case | Mutation applied | Caught by |
| --- | --- | --- | --- |
| M-N34 | N34 | The full Object ID sent as a prop, masked in the screen | `test_no_people_screen_carries_a_full_object_id_or_tenant` |
| M-N35 | N35 | Reveal accepts a third field, `email` | `test_reveal_accepts_exactly_two_field_names` |
| M-N35b | N35 | The refusal names the rejected field, turning the endpoint into a way of asking which columns exist | as above |
| M-N36 | N36 | An email added to a recorded security event | `test_no_people_event_carries_a_personal_identifier` |
| M-N37 | N37 | The email becomes an editable input on the record | `test_the_directory_fields_are_presented_as_read_only` |
| M-N37b | N37 | The note saying Microsoft owns the identifier is removed | as above |
| M-N38a | N38 | The page limit raised to 1,000 | `test_search_filter_and_pagination_work_against_volume` |
| M-N38b | N38 | The status filter ignored | as above |
| M-N38c | N38 | The *Not assigned* filter ignored | as above |
| M-N38d | N38 | The Current/Past membership filter ignored | `test_the_group_screens_search_filter_and_paginate` |
| M-N38e | N38 | Unassigned people dropped from the list, making the filter meaningless | `test_search_filter_and_pagination_work_against_volume` |
| M-empty | — | *"Nobody has ever been in this group"* shown whenever the filter matches nobody | `test_an_empty_search_result_is_not_reported_as_an_empty_group` |
| M-N39 | N39 | A raw `#991547` put into `.org-pagination` | `test_the_people_rules_use_theme_aware_tokens_rather_than_raw_hexes` |

## The two guards added after review

| ID | Mutation applied | Caught by |
| --- | --- | --- |
| M-scope | `refuseIfOutsideOrganisation()` disabled | `test_a_record_of_another_organisation_is_not_found` |
| M-member-group | The `group_id` comparison in `removeMember` removed | `test_a_membership_cannot_be_ended_through_a_different_group` |

## The group duplicate-name guard, added after reading the test script

| ID | Mutation applied | Caught by |
| --- | --- | --- |
| M-N44b | `refuseIfTaken()` dropped from `create()`, so `groups_org_name_uq` surfaces to the administrator | `test_no_group_refusal_exposes_a_database_error` |
| M-N44c | The same, dropped from `update()` | as above |
| M-N44d | The `whereKeyNot` that lets a group keep its own name removed, so saving a group unchanged refuses | as above |

## The reworked secret-length guard

Not P1-03's, but reworked because it blocked this PR — see
`P1-03-USERS-GROUPS-VERIFICATION.md` §7b.

| ID | Mutation applied | Caught by |
| --- | --- | --- |
| M-secret-length | `SecretPresence::inWords()` returns `"Present (33 characters)"` | `test_the_secret_is_reported_as_presence_only` |
| M-secret-props-scope | The props extraction returns nothing at all, so the guard would have nothing to look at | as above |

## 12. QA Execution Tracker

Sequential checklist:

[v] QA-001 `/up` JSON 200
[v] QA-002 unauthenticated `/` behavior
[v] QA-003 env/setup sanity
[v] QA-004 baseline `php artisan test`
[v] QA-005 login success
[v] QA-006 login invalid
[v] QA-007 throttle behavior
[v] QA-008 logout
[v] QA-009 protected redirect
[v] QA-010 email verification
[v] QA-011 forgot password
[v] QA-012 reset password valid
[v] QA-013 reset password invalid
[v] QA-014 registration enabled
[v] QA-015 registration disabled 404
[v] QA-016 profile prefilled
[v] QA-017 profile update
[v] QA-018 profile invalid input
[v] QA-019 password change valid
[v] QA-020 password change invalid
[v] QA-021 locale switch
[] QA-022 API profile read
[] QA-023 API profile update
[x] QA-024 sidebar visibility
[v] QA-025 sidebar flag-off behavior
[v] QA-026 sidebar permission behavior
[v] QA-027 404 page
[v] QA-028 create role
[v] QA-029 duplicate role
[v] QA-030 assign permissions
[x] QA-031 delete in-use role -> role deleted, remove role in user
[v] QA-032 restore role
[v] QA-033 force delete role
[v] QA-034 bulk delete roles
[v] QA-035 direct URL no perm
[v] QA-036 direct URL flag off
[v] QA-037 create permission
[v] QA-038 duplicate permission
[v] QA-039 delete assigned permission
[v] QA-040 restore/force delete permission
[v] QA-041 create user
[v] QA-042 invalid user fields
[v] QA-043 duplicate email
[x] QA-044 edit user role/permissions
[v] QA-045 soft delete user
[v] QA-046 restore user
[v] QA-047 force delete user
[v] QA-048 bulk delete/restore users
[v] QA-049 lock user
[v] QA-050 unlock user
[v] QA-051 reset user password
[v] QA-052 users page unauthorized
[v] QA-053 users page feature off
[v] QA-054 sessions list
[v] QA-055 logout others
[] QA-056 create API token
[] QA-057 delete API token
[] QA-058 deleted token rejects API
[] QA-059 token scopes if any
[v] QA-060 audit record created
[v] QA-061 audit export
[v] QA-062 notifications list
[v] QA-063 notification read/unread
[] QA-064 logs page authorized
[] QA-065 logs page unauthorized/off
[v] QA-066 settings page
[v] QA-067 registration toggle
[v] QA-068 translation edit
[x] QA-069 translation add
[v] QA-070 settings direct 403
[v] QA-071 toggle flag navigation
[v] QA-072 toggle flag route 404
[v] QA-073 toggle flag 403
[] QA-074 API flag behavior
[v] QA-075 dashboard admin
[v] QA-076 dashboard limited
[v] QA-077 dashboard feature-off safety
[] QA-078 API login valid
[] QA-079 API login invalid
[] QA-080 API me
[] QA-081 API users no perm
[] QA-082 API users flag off
[] QA-083 API roles/permissions gating
[] QA-084 API token CRUD
[] QA-085 API password change
[] QA-086 API email verify/resend
[] QA-098 RBAC end-to-end
[] QA-099 flag toggle + access
[] QA-100 registration setting
[] QA-101 translation cross-page
[] QA-102 API token revocation immediate
[] QA-103 user delete/restore/force-delete
[] QA-104 lock/unlock login
[] QA-105 password change login
[] QA-106 plan CRUD
[] QA-107 user plan assignment
[] QA-108 license activation
[] QA-109 license expiration
[] QA-110 dummy billing checkout
[] QA-111 billing cancel
[] QA-112 admin billing KPIs
[] QA-113 webhook fail-closed
[] QA-114 cross-user license isolation
[] QA-115 regression checklist
[] QA-116 edge case pass
[] QA-117 final sign-off
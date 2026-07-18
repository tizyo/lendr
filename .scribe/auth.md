# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {STAFF_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Obtain a token via **POST /api/v1/auth/login**. Include it in the Authorization header as `Bearer {token}`.

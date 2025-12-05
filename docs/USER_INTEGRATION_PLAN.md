# YFEvents + Webcat User Integration Plan

## Concept

Single user table in YFEvents serves as the source of truth. Users with certain roles automatically have Webcat access.

## User Table Enhancement

```sql
-- Add to YFEvents users table
ALTER TABLE users
ADD COLUMN is_webcat_user BOOLEAN DEFAULT FALSE,
ADD COLUMN webcat_role ENUM('vendor', 'staff', 'admin') NULL;
```

## Role Mapping

| YFEvents Role | Webcat Access | Webcat Role |
|---------------|---------------|-------------|
| is_yf_staff = true | Automatic | staff |
| is_yf_vendor = true | Automatic | vendor |
| is_admin = true | Automatic | admin |
| is_webcat_user = true | Manual grant | vendor |

## Integration Options

### Option A: Webcat Authenticates Against YFEvents DB (Recommended)

```
┌─────────────────┐         ┌─────────────────┐
│     Webcat      │         │    YFEvents     │
│  (Node.js)      │         │     (PHP)       │
│                 │         │                 │
│  webcat_db      │         │  yakima_finds   │
│  - items        │         │  - users ◄──────┼── Source of truth
│  - item_images  │         │  - events       │
│  - messages     │         │  - shops        │
└────────┬────────┘         │  - yfc_*        │
         │                  └─────────────────┘
         │
         │  Login request
         ▼
┌─────────────────┐
│ YFEvents users  │
│     table       │
│                 │
│ Check:          │
│ - is_yf_staff   │
│ - is_yf_vendor  │
│ - is_webcat_user│
└─────────────────┘
```

**How it works:**
1. User goes to webcat.yakimafinds.com
2. Enters YFEvents credentials (email/password)
3. Webcat backend queries YFEvents `users` table
4. If user has webcat access (staff/vendor/explicit), allow login
5. Create JWT with user info and webcat role

**Webcat auth code change:**
```typescript
// backend/src/services/authService.ts
import mysql from 'mysql2/promise';

const yfeventsDb = mysql.createPool({
  host: 'localhost',
  database: 'yakima_finds',
  user: 'yfevents',
  password: process.env.YFEVENTS_DB_PASS
});

async function authenticateUser(email: string, password: string) {
  const [rows] = await yfeventsDb.query(`
    SELECT id, email, password_hash, name,
           is_yf_staff, is_yf_vendor, is_admin, is_webcat_user,
           CASE
             WHEN is_admin = 1 THEN 'admin'
             WHEN is_yf_staff = 1 THEN 'staff'
             WHEN is_yf_vendor = 1 OR is_webcat_user = 1 THEN 'vendor'
             ELSE NULL
           END as webcat_role
    FROM users
    WHERE email = ?
    AND (is_yf_staff = 1 OR is_yf_vendor = 1 OR is_admin = 1 OR is_webcat_user = 1)
  `, [email]);

  if (rows.length === 0) return null;

  const user = rows[0];
  const validPassword = await bcrypt.compare(password, user.password_hash);

  if (!validPassword) return null;

  return {
    id: user.id,
    email: user.email,
    name: user.name,
    role: user.webcat_role
  };
}
```

### Option B: SSO via Shared JWT

Both systems accept the same JWT token format:

```
┌─────────────────┐         ┌─────────────────┐
│     Webcat      │◄───┐    │    YFEvents     │
└─────────────────┘    │    └────────┬────────┘
                       │             │
                       │    ┌────────▼────────┐
                       └────┤  Shared JWT     │
                            │  Secret Key     │
                            └─────────────────┘
```

User logs into either system, gets JWT valid for both.

### Option C: OAuth2/API Authentication

YFEvents provides `/api/auth/verify` endpoint:

```
Webcat ──► POST /api/auth/verify ──► YFEvents
           {email, password}

           Returns: {user_id, role, token}
```

## Recommended Approach: Option A

**Pros:**
- Simplest to implement
- Single source of truth
- No API latency for auth
- Works even if YFEvents web is down

**Implementation Steps:**

1. **Add columns to YFEvents users table**
   ```sql
   ALTER TABLE users
   ADD COLUMN is_webcat_user BOOLEAN DEFAULT FALSE;
   ```

2. **Auto-flag existing staff/vendors**
   ```sql
   UPDATE users SET is_webcat_user = TRUE
   WHERE is_yf_staff = TRUE OR is_yf_vendor = TRUE;
   ```

3. **Update Webcat auth service**
   - Connect to yakima_finds database
   - Query users table for authentication
   - Map YFEvents roles to Webcat roles

4. **Remove Webcat's local user management**
   - Keep users table in webcat_db for foreign keys
   - But don't create users there
   - On first login, create shadow record if needed

5. **Update YFEvents admin**
   - Add "Webcat Access" checkbox to user management
   - When creating YF staff/vendor, auto-enable webcat

## User Management UI

In YFEvents Admin → Users:

```
┌─────────────────────────────────────────────────────────┐
│ Edit User: John Smith                                    │
├─────────────────────────────────────────────────────────┤
│ Email: john@example.com                                  │
│ Name: John Smith                                         │
│                                                          │
│ Roles:                                                   │
│ ☐ Admin                                                  │
│ ☐ Moderator                                              │
│ ☐ YF Staff          ──┐                                  │
│ ☐ YF Vendor         ──┼── Auto-grants Webcat access      │
│ ☐ Business Owner      │                                  │
│ ☐ Seller              │                                  │
│                       │                                  │
│ Platform Access:      │                                  │
│ ☑ Webcat Access ◄─────┘ (auto-checked if staff/vendor)   │
│ ☐ Communication Hub                                      │
│                                                          │
│ [Save Changes]                                           │
└─────────────────────────────────────────────────────────┘
```

## Data Flow Example

**Scenario: New vendor onboarding**

1. Admin creates user in YFEvents
2. Checks "YF Vendor" role
3. System auto-enables:
   - Communication Hub access
   - Webcat access
4. Vendor receives welcome email with:
   - YFEvents login (for communication)
   - Webcat login (same credentials)
5. Vendor can now:
   - Chat in Communication Hub
   - List items in Webcat

## Migration Plan

1. **Phase 1: Database prep**
   - Add is_webcat_user column to YFEvents
   - Flag existing staff/vendors

2. **Phase 2: Update Webcat auth**
   - Add YFEvents DB connection
   - Modify login to check YFEvents users
   - Test with existing accounts

3. **Phase 3: Remove Webcat user management**
   - Disable registration on Webcat
   - Point "manage users" to YFEvents admin
   - Keep shadow records for FK relationships

4. **Phase 4: Single sign-on (future)**
   - Shared session/JWT
   - Login once, access both

## Benefits

- **Single user management**: Admins manage users in one place
- **Consistent permissions**: Staff/vendor status applies everywhere
- **Easier onboarding**: Create user once, access everywhere
- **Audit trail**: All auth goes through YFEvents users table
- **Future-proof**: Easy to add more integrated systems

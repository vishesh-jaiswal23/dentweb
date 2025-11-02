# Admin User Management Overview

The Dakshayani admin portal now treats user records as two distinct umbrellas:

- **Integral Users** — Admins, employees, installers, and referrers share the same account lifecycle. They are managed from the Integral Users tab where admins can create, edit, activate/deactivate, and reset credentials.
- **Customers** — Leads, ongoing projects, and installed systems sit on a single timeline controlled by the `state` field. Customers enter as **lead**, advance to **ongoing** when an employee and system plan are assigned, and finish as **installed** once the handover date is recorded (unlocking complaint logging).

State changes only move forward (Lead → Ongoing → Installed) and are reserved for administrators inside the Customers tab. All supporting metadata—assignments, system details, quotes, installation progress, warranties, and subsidy references—travels with the customer record as it progresses.

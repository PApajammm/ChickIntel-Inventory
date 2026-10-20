# ChickIntel Inventory Project Rubric Assessment

**Assessment date:** October 20, 2026  
**Project:** ChickIntel: Smart Chicken Poultry Health Monitoring and Management System Using CNN Algorithm — Inventory module

This document records the current implementation status of the Inventory module against the supplied project rubric. Status reflects what is implemented and verified in this repository and its local XAMPP development setup; it does not claim unverified deployment or team-process work.

## Status summary

| Rubric area | Status | Evidence and remaining work |
|---|---|---|
| **I.a — CRUD operations** | ✅ Implemented | Authenticated inventory create, read, update, restock, and delete operations are available through the PHP API and MariaDB/MySQL. CRUD and per-user data isolation were tested. |
| **I.b — Navigation and screen flow** | ✅ Implemented | Login, registration, protected inventory, and inventory history screens are routed in the standalone Angular app. |
| **I.c — Android emulator/device compatibility** | 🟡 Partial — not verified | Capacitor dependencies and configuration are present, but an Android native project has not been generated and no emulator/device run has been completed. Generate the Android project and test the primary flows on an emulator or device. |
| **II.a — Firebase or XAMPP setup/connectivity** | ✅ Implemented for local XAMPP development | Angular connects to the PHP API served by Apache, and the API reads/writes the MariaDB/MySQL database. The local API health endpoint returned HTTP 200. This is a development setup, not a production deployment. |
| **II.b — Real-time communication/data sync** | ✅ Implemented — near-real-time polling | The Inventory and History screens poll the authenticated API every five seconds while visible and pause polling while hidden. Cross-session testing confirmed inventory changes and history entries made in a second authenticated session appeared in the open app. This is HTTP polling, not push-based WebSocket/Firebase synchronization. |
| **III.a — Modular/readable code and meaningful comments** | 🟡 Mostly implemented | UI pages, API/auth services, inventory state, and PHP API are separated into modules. Build and lint pass. Comments are used where useful rather than added mechanically; a broader manual code-quality review has not been recorded. |
| **III.b — Effective branching and merging strategy** | 🟡 Partially demonstrated | A feature branch was pushed and merged through GitHub [pull request #1](https://github.com/PApajammm/ChickIntel-Inventory/pull/1). This demonstrates one branch-and-merge cycle; continue using reviewed feature branches and record team reviews/contributions to show a sustained strategy. |
| **IV.a — Visuals and theme consistency** | ✅ Implemented for responsive web UI | The inventory, history, login, and registration screens share the ChickIntel green theme and responsive layouts. Desktop and mobile browser layouts were checked; native Android UI behavior remains unverified. |
| **IV.b — User-friendly navigation and flow** | ✅ Implemented for browser flows | Login/registration, protected routes, inventory actions, history navigation, validation, and sign-out are implemented. Android-specific usability has not been tested. |
| **V.a — Clear verbal presentation** | ⚪ Not verifiable from source | Prepare and deliver a presentation explaining the project goal, inventory features, backend flow, and the limits of local polling. |
| **V.b — Work assignment distribution** | ⚪ Not verifiable from source | Record team members, assigned responsibilities, and contributions in project documentation or presentation materials. |
| **V.c — Working app demonstration** | 🟡 Local web demo verified | The app and XAMPP API have been exercised locally, including cross-session sync. For an in-person demonstration, confirm Apache/MySQL availability and test with demo accounts beforehand. Android demonstration is not verified. |
| **VI.a — Project summary and purpose** | 🟡 Partial | This assessment identifies the project and its Inventory scope; a complete project overview covering goals, architecture, setup, and features should be added to the main project documentation. |
| **VI.b — Screenshots and architecture diagrams** | ❌ Not included | Add current app screenshots and an architecture diagram showing Ionic Angular → PHP API (Apache) → MariaDB/MySQL. |

## Branching and merging workflow

Use `master` as the stable branch and create a short-lived branch for each focused feature, fix, or documentation change. Commit related work with descriptive messages, push the branch, and open a pull request targeting `master`. Run the relevant build, lint, and tests before requesting review. Review the diff and checks before merging; for team work, request review from another member where possible. Delete merged feature branches after confirming the merge.

**Current evidence:** GitHub pull request [#1](https://github.com/PApajammm/ChickIntel-Inventory/pull/1) for `docs/branching-strategy-evidence` was merged into `master` on October 20, 2026. Continue using the workflow on future work and record contributors/reviewers for the team-process portion of the rubric.

## Live-sync verification

The Inventory and History screens share a five-second polling loop in `src/app/inventory/inventory.service.ts`. A second authenticated session was used to create and restock an item; the open Inventory screen reflected the quantity change, and the History screen displayed the new activity. The temporary verification account and its data were removed after the test.

Polling provides near-real-time updates but introduces a delay of up to approximately five seconds and makes periodic HTTP requests. If the rubric evaluator requires server-pushed updates rather than periodic refresh, use a WebSocket-capable service or another approved push transport and verify it with multiple clients.

## Local demonstration steps

1. Start Apache and MySQL/MariaDB in XAMPP.
2. Start the frontend from the repository root:

   ```powershell
   ionic serve --host 127.0.0.1 --port 4200
   ```

3. Open `http://127.0.0.1:4200/` and sign in.
4. Open the same account in a separate browser session. Add or restock an item in one session and confirm the other updates within about five seconds.
5. Open Inventory History and confirm that the activity appears there as well.

Use the same `127.0.0.1` host consistently for the frontend and configured local API origin.

## Validation recorded

- Angular production build: passed.
- Angular lint: passed.
- Existing unit tests: 2 passed.
- PHP API syntax check: passed.
- Local API health endpoint: HTTP 200.
- Cross-session inventory and history polling: passed.
- Android emulator/device test: not performed.

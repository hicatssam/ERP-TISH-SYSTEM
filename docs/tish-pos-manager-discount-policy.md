# TISH POS — Manager discount safeguard

**Stage:** Server-enforced high-discount authorization. This is NOT yet a separate manager-to-cashier approval-code flow.

Default policy:
- A manually entered percentage above **10%** requires a manager-capable signed-in session.
- A manually entered fixed discount above **₪20** requires a manager-capable signed-in session.
- Both cutoffs are configurable using `TISH_POS_MANAGER_DISCOUNT_PERCENT` and `TISH_POS_MANAGER_DISCOUNT_FIXED`; restart/rebuild config cache after changing them.
- The existing minimum 8-character reason requirement remains mandatory.
- A manager-capable user is a trusted `Admin`, a `Branch Manager`, or one explicitly granted `restaurant_pos.discount.approve`.
- Authorization runs both in FormRequest and in RestaurantOrderService, so a modified browser POST cannot bypass it.
- The cashier UI displays the limit immediately when exceeded.

**What this does not provide:** A manager credential popup, short-lived delegated approval token, or separate manager sign-off of an already composed cashier order. Until that is built, managers must sign in using their own account to submit a sensitive discounted transaction, leaving attribution in the order and activity log. Never share manager passwords with cashiers.

**Test gate:** `PosCashierShiftSimulationTest` covers lower-limit sales, forbidden over-limit cashiers, direct-service bypass attempts, manager ability and fixed-amount threshold. Only use production after GitHub CI passes and the restaurant operator agrees to the configured policy.

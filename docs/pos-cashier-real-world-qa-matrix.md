# TISH POS — real-world cashier QA coverage matrix
Audit branch: `audit/pos-real-world-scenarios-20261118`

**Safety:** Tests use Laravel `RefreshDatabase` on isolated CI databases. Never execute tests with a production DB URL. The production application and cash balances are not modified by this PR.

## Evidence / status conventions
- **Automated new:** test in `tests/Feature/Restaurant/PosCashierShiftSimulationTest.php`. Run results must be verified on the latest PR commit; do not infer PASS from its presence.
- **Existing regression:** prior tests exist in the specified source. CI success indicates the suite passed, but is not an independent simulated browser/cashier test.
- **Code only:** an implementation path was inspected but no test was run for this exact scenario.
- **Not tested:** neither a passing isolated test nor live browser evidence is available. This is an outstanding release gate, not a PASS.

## Cashier queue — ordinary orders
| ID | Scenario | Coverage | Evidence |
|---|---|---|---|
| A01 | One burger | Automated new POS-01 | Single paid line, stock, invoice |
| A02 | Three distinct products | Automated new POS-02 | Three lines, one payment |
| A03 | Ten burgers | Automated new POS-03 | Exactly ten stock deductions |
| A04 | Burger with paid modifiers | Automated new POS-48 | Modifier required, server snapshot, payment and invoice totals |
| A05 | Remove ingredient via modifier | Code only | Modifier selection and kitchen notes; needs physical-kitchen acceptance |
| A06 | Out-of-stock product | Automated new POS-06/POS-07/POS-23 | Rejected with no partial writes |
| A07 | Variants / sizes | Automated new POS-46/POS-47 | Unsized order rejected; selected variant price from database |
| A08 | Change mind before sending | Code only | Cart plus/minus/remove; browser test outstanding |
| A09 | Repeat previous order | Not tested | Recent-orders drawer is not an explicit clone-order operation |

## Editing / state transitions
| ID | Scenario | Coverage | Evidence |
|---|---|---|---|
| B01 | Add item to unconfirmed draft | Existing regression | `OrderUpdateInventoryGuardTest` |
| B02 | Remove item from unsent cart | Code only | `rb-cart-remove` and cart listener |
| B03 | Modify item/notes after kitchen handoff | Automated new POS-31/POS-32 for quantity/notes | Blocked after dispatch; deleting individual lines still not tested |
| B04 | Increase/decrease quantity | Automated new POS-10 + existing regression | Discount and invoice reconciliation |
| B05 | Change modifiers after submit | Not tested | Must verify KDS + consumption snapshots |
| B06 | Cancel unpaid confirmed order | Existing regression | `TishRecipeStockFlowTest` |
| B07 | Cancel one item in kitchen | Not tested | Requires explicit KDS line cancellation policy |
| B08 | Edit fully paid order | Automated new POS-10 + existing regression | Repricing and invoice balance |
| B09 | Edit during preparation | Not tested | Concurrency and KDS state race |
| B10 | Reopen closed order | Not tested | Needs manager authorization workflow |
| B11 | Cancel paid order before refund | Automated new POS-19 | Must reject cancellation |

## Payment and accounting
| ID | Scenario | Coverage | Evidence |
|---|---|---|---|
| C01 | Full cash payment | Automated new POS-41 | POS cash payment contributes correctly to cashier session closing balance |
| C02 | Card / wallet | Automated new POS-01/POS-08/POS-18 | Real card gateway settlement not tested |
| C03 | Mixed cash + card in one checkout | Not confirmed | POS form takes one payment method |
| C04 | Cash change for overpayment | Not confirmed | Server caps recorded payment at order total |
| C05 | Partial payment / deposit | Existing implementation; not isolated POS test | PaymentArrangement + PaymentService |
| C06 | Rejected transfer | Existing regression | `OrderBankTransferFlowTest` |
| C07 | Network fails after commit | Automated new POS-04/POS-05 | Safe exact retry; actual network fault injection outstanding |
| C08 | Double click within browser | Frontend structural regression + server POS-04 | Actual touch/Playwright test pending |
| C09 | Void / correct payment | Existing regression | `PaymentService::correct` |
| C10 | Partial/full refund | Automated new POS-33/POS-34/POS-35/POS-36 | Unauthorized, partial, excessive, full refund then order cancellation |
| C11 | Fixed discount | Automated new POS-16/POS-17 | Reject over-total; correct invoice/payments |
| C12 | Percentage discount | Automated new POS-15/POS-10 | Reject >100%; preserve channel discount |
| C13 | Coupon code | Not confirmed | No verified POS coupon code flow |
| C14 | Unauthorized discount | Code only | Manual reason required; manager approval not verified |
| C15 | Paid amount greater than total | Code only | `OrderService::createPaymentIfNeeded` rejects excess |
| C16 | Negative amount or zero/negative quantity | Automated new POS-11/POS-27 | Validation rejects |
| C17 | Financial correction with open/closed cash day | Existing regression | `DailyCashReconciliationTest` |

## Peak hour / failures / concurrency
| ID | Scenario | Coverage | Evidence |
|---|---|---|---|
| D01 | Ten customers in line | Not tested | Load test and click count required |
| D02 | Five KDS tickets preparing | Not tested | Concurrent station test required |
| D03 | Order changed while kitchen updates | Not tested | P1 race audit |
| D04 | Rush checkout | Code only | F2 search, F8 checkout, cached product catalog |
| D05 | Delayed order complaint | Code only | KDS state + public tracking |
| D06 | Two cashiers at once | POS-21 and MySQL POS-RACE-01/POS-RACE-02 | Two actual PHP processes with independent MySQL connections; no browser load test |
| D07 | Cashier closes tab before saving | Code only | Local draft exists; browser crash restore untested |
| D08 | Network outage / server timeout | Automated new POS-04 retry | Actual fault injection untested |
| D09 | Printer offline after payment | Code only | UI shows print warning; browser/hardware test needed |
| D10 | Stock runs out between cart and submit | POS-06/POS-23 + MySQL POS-RACE-02 | Two independent orders race for last inventory unit; one sale only |
| D11 | Two distinct legitimate orders | Automated new POS-20 | Correct independent payments |
| D12 | Duplicate browser POST | POS-04/POS-05 + MySQL POS-RACE-01 | Concurrent PHP processes use same request token; one order/payment/invoice/stock movement |

## Order channels and customer data
| ID | Scenario | Coverage | Evidence |
|---|---|---|---|
| E01 | Dine-in / table | Automated new POS-42/POS-43/POS-44 | Open correct table session, require table, reject foreign branch table |
| E02 | Takeaway | Automated new POS-01 | End-to-end |
| E03 | Delivery | Automated new POS-45 | Delivery and outdoor orders remain distinct |
| E04 | Pickup / outdoor | Automated new POS-45 | Separate outdoor request persisted |
| E05 | Phone / web | Code only | `RestaurantServiceType` |
| E06 | Public QR orders | Existing regression | `CustomerMenuOrderLifecycleTest` + POS QR drawer |
| E07 | Wrong branch | Automated new POS-22 | Reject cross-branch |
| E08 | Forged item price | Automated new POS-12 | Reprice from database |
| E09 | Invalid product / modifier | Automated new POS-13/POS-24 | Reject before payment |
| E10 | Disabled payment method / channel | Automated new POS-25/POS-26 | Reject |

## Drawer, cashier shifts and end-of-day
| ID | Scenario | Coverage | Evidence |
|---|---|---|---|
| F01 | Open cashier cash drawer shift | Automated new POS-37 | `CashSession` opened with float; second open refused |
| F02 | Opening float | Automated new POS-37/POS-38 | Correct float; rejects negative float |
| F03 | Daily sales reconcile | Automated new POS-40/POS-41 + existing cash-day tests | Cash vs wallet reconciled separately |
| F04 | Refund and manual cash movement | Existing cash day regression | `DailyCashReconciliationTest` |
| F05 | Switch cashier with open cart | Not tested | Requires multi-user/browser test |
| F06 | Logout with open order | Not tested | Browser session expiry test |
| F07 | Close cash day with outstanding orders | Not tested | Cash day vs open order policy |
| F08 | Shortage / overage | Automated new POS-39 (shortage) + existing cash-day tests | Cashier shift variance recorded; overage still needs specific test |
| F09 | Closing report | Automated new POS-39/POS-40/POS-41 | Closing totals computed from cashier shift; printed report UX untested |
| F10 | Edit closed cashier session | Automated new POS-39 + existing cash-day tests | Second close denied, existing cash day closed-guard tested |

## Latest execution evidence (2026-11-18)
- **48/48 new Laravel POS feature scenarios PASS** in the latest full-suite log; one separate WebAuthn registration test failed intermittently in run #44362574445.
- **MySQL POS-RACE-01 / POS-RACE-02 PASS** in the GitHub Actions step `MySQL — two-process POS duplicate and last-stock races` on run #44364827855.
- Regression script: `scripts/qa/pos-concurrency.php` requires MySQL, `APP_ENV=testing`, and a database name ending in `_test`. It uses two separate PHP workers and verifies payment, invoice, order, and inventory counts.
- Tests do **not** yet cover actual physical card settlement, hardware printing, touchscreen usability, genuine browser interruptions, or all shift handover edge cases.

## Highest priority issues
1. **P0:** Duplicate creation after HTTP timeout. Code mitigation: `pos_request_token` validated UUID, `orders.public_request_token` unique index, actor+branch+payload-hash guard, repeat returns original. **MySQL POS-RACE-01 PASS with two actual PHP processes**; clients not sending tokens and browser-level fault injection are still outstanding.
2. **P1:** A confirmed order quantity edit could discard manual/channel discount by resetting `total_amount` to `subtotal`. Repricing via `OrderSalesChannelService::apply()` is in this PR with POS-10 regression.
3. **P1:** Kitchen edits previously validated before the order row lock. The check now happens inside the order lock; POS-31/POS-32 validate queued-ticket behavior. True simultaneous KDS state transitions versus order changes remain untested.
4. **P1:** Mixed tender, manager discount approval, and one-line post-KDS adjustment need explicit business policy/implementation checks.
5. **P2:** Full tablet/mobile/touchscreen measurement (tap counts, time-to-payment, modal visibility) remains a browser/device test.

## Release gate
- Run full ERP CI including these feature and frontend tests.
- Run Finance MySQL Integration against ephemeral GitHub Actions MySQL 8.0.
- Run PDF Layout Regression.
- Run load/concurrency tests (2 cashiers, KDS transitions) and browser tests (double-click, lost response, printer blocked, tablet).
- Reconcile order, items, payments, invoice, stock consumption, kitchen tickets, and ledger against the same test order.
- No merge into main or deployment until outstanding P0/P1 cases have defined outcomes.

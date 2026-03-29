hDelivery Integration: Teams agreed on API for automating order handoff, reducing manual errors in status updates.
Pricing Structure: Both sides set tiered pricing with a base fee and per-mile charges, discussing maximum caps.
Fee Calculation: Merchants will calculate delivery fees during checkout, sending finalized costs to the delivery partner.
Technical Integration: REST APIs and webhooks will handle order communication, with current location checks every 20–60 seconds.
PIN Verification: Each delivery will use a unique PIN to confirm identity and mark orders as delivered.
Timeline Expectations: Beta testing for the merchant app in two weeks; API integration also expected to be ready in that timeframe.

Notes
Delivery Integration and Workflow
The teams agreed on integrating delivery operations via API to automate order handoff and status updates, aiming to reduce manual steps and errors (00:12).

API-driven order handoff will trigger once an order is placed and ready for pickup, sending pickup and drop-off locations plus cost details to the delivery partner.
Dacam Comm emphasized receiving notifications early, even before orders are ready, to start driver assignment promptly.
The delivery team will send back status updates, including pickup, out-for-delivery, and completion signals, to keep merchants and customers informed in real time.
This approach reduces manual intervention by merchants and streamlines the delivery flow from order placement to completion.
The order lifecycle and merchant app workflow were reviewed to clarify system states and identify backend issues (00:08).

Orders transition through stages: placed → preparing → ready for pickup → going to pickup → out for delivery → delivered → completed.
Admins or riders get notifications at key transitions like ready for pickup and going for pickup.
Some backend glitches affecting order visibility were noted and flagged for technical follow-up.
This shared understanding supports smoother API integration and operational alignment.
Pricing Model and Delivery Fee Structure
Both sides outlined their pricing models, agreeing on a base radius with fees and additional per-mile charges beyond it, but debated on applying maximum caps (00:17).

Dacam Comm explained their tiered pricing: a base fee covers up to a certain mileage (e.g., 5 miles), with incremental charges per mile after that.
Rudolf showed a similar model with configurable base radius, base fee, per-mile fee, and an optional maximum cap.
The teams agreed to either remove the maximum cap or set it realistically based on the furthest delivery distance to avoid limiting orders beyond certain ranges.
This pricing flexibility ensures fair charges while accommodating diverse delivery distances.
They decided the delivery fee calculation will be done on the merchant’s side based on an agreed pricing model (00:34).

The merchant app will calculate fees upfront during checkout and collect payment from customers.
The delivery partner will receive the finalized cost with order details to create and dispatch driver jobs without recalculating fees.
This reduces network back-and-forth and minimizes job mismatches or broken orders.
Pricing changes will require prior joint review to maintain agreement and avoid unilateral changes.
Technical API and Integration Details
The integration will primarily use REST APIs and webhooks to communicate order and delivery status changes between systems (00:33).

Orders placed trigger webhooks posting order details to the delivery partner’s system to create driver jobs automatically.
Status updates like rider assignment, pickup, and delivery completion are sent back via webhooks to update merchant systems accordingly.
The delivery partner currently lacks live GPS tracking but uses periodic location checks every 20–60 seconds to update progress (00:25).
The teams agreed to create a WhatsApp group to facilitate ongoing technical discussions, testing, and issue resolution (00:39).
The delivery verification process involves a PIN system generated at job creation to confirm successful delivery (00:41).

The delivery partner generates a unique PIN per job and sends it back to the merchant system to display to customers.
The driver obtains the PIN from the recipient upon delivery to verify identity and confirm completion.
This PIN confirmation triggers update calls to the merchant system to mark orders as delivered.
The merchant side considers implementing the PIN system in a future app phase but recognizes its value now based on delivery partner capabilities.
Operational and Testing Timelines
The merchant app is nearing completion and plans to start beta testing with external users in about two weeks, aiming for a public release by end of April or early May (00:26).

Live payment processing is already tested successfully with only minor patches pending.
The timeline also aligns with onboarding external investors to support future growth.
The integration with the delivery partner will be tested early to identify and fix issues before full launch.
Both teams emphasized close collaboration during this phase to ensure readiness.
The delivery partner expects the API integration to be ready for testing within two weeks to allow sufficient time for adjustments before going live (00:49).

They highlighted the importance of seamless API communication to reduce operational risks and job failures.
Manual rider assignment only occurs if automatic assignment fails despite price incentives for drivers.
Pricing multipliers increase driver offers to encourage acceptance if jobs remain unclaimed, ensuring service reliability.
This operational design balances automation with fallback manual controls to maintain delivery performance.

Action items
Rudolf Ehumah
Share screen and present UrbanDrop order and delivery flow to delivery partner (05:34)
Fix currency display issue showing Euros instead of Pounds on app (24:31)
Create WhatsApp group with relevant UrbanDrop and delivery partner team members for integration communication and testing coordination (39:31)
Proceed with beta app release for external testing within two weeks and align public launch for end of April/early May (26:23)
Provide delivery partner with API documentation and collaborate on developing integration per agreed webhook system for order and job status updates (27:28)
Dacam Comm
Develop API to receive order details from UrbanDrop and automatically create delivery jobs once order is placed and paid (27:28)
Provide PIN generation mechanism upon job creation and send PIN via API to UrbanDrop for customer display and delivery verification (42:36)
Implement periodic location update system for driver positions during deliveries; work on enhancing technical live tracking features long term (25:04)
Set up price multiplier system for job assignment to riders and manually assign jobs if riders do not accept notifications promptly (30:09)
Respond to UrbanDrop with updated job status and confirmation upon delivery including API postbacks (47:04)
Gilbert Elikplim Kukah
Clarify and provide details on API protocols and pricing model handling for seamless integration (35:36)
Coordinate with delivery partner on webhook implementation and API data exchanges (47:04)

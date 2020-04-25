## What and Why?

This is an extension to allow importing of Attendees from CSV to Event Tickets. This requires both Event Tickets and The Events Calendar to be active.
 
## How?

Once you activate this extension, you will see new content type options when you go to Events > Import and choose the CSV origin.

* RSVP Attendees
* Ticket Attendees (Tribe Commerce)
* Ticket Attendees (Easy Digital Downloads)
* Ticket Attendees (WooCommerce)

The Attendees import option shown will depend on whether you have the associated plugin(s) activated that enable them. Easy Digital Downloads and WooCommerce must be activated in order to use them, but you will also need Event Tickets Plus to create tickets for those attendees.

### Supported CSV columns

* `event_name` - Event Name or ID or Slug. This is **required** unless you manually chose the "Event" to use when importing.
* `ticket_name` - Ticket Name or ID (**required**).
* `attendee_name` - Attendee Name (**required**).
* `attendee_email` - Attendee Email (**required**).
* `display_optin` - Opt-in Display (default: `0`). This can be any of the following values: `0`, `1`, `off`, `on`, `no`, `yes`
* `going` - Going or Not Going (default: `yes`). This is only used for the RSVP Attendees import. This can be any of the following values: `0`, `1`, `off`, `on`, `no`, `yes`, `going`
* `user_id` - User ID (default: `0`). This value should be an integer.

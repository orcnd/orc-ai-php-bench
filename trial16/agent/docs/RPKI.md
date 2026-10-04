# Route origin validation

We implement RFC 6811 origin validation against ROAs (RFC 6482, RFC 6483).
Our routers use the result to drop `invalid` routes, so a wrong `valid` is a
hijack let through and a wrong `invalid` is an outage.

* A ROA *covers* a route when the ROA prefix contains the route prefix.
* A covering ROA *matches* when it authorises the route's origin AS and
  length.
* `valid`: some covering ROA matches. `invalid`: covered, but nothing
  matches. `not-found`: nothing covers the route.

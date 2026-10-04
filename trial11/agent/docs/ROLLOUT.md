# How releases roll out

* Rolling deploy over ~2 weeks: V1 (release 4.x, `legacy/V1`) and V2 nodes
  serve traffic at the same time and share the same document store and
  the same message queue. We cannot roll back data, only code.
* V1 nodes keep reading and writing customer documents (customers change
  their address on V1 pages) and keep producing and consuming
  `invoice.requested` messages. Any message may be handled by a V1 or a V2
  worker, in either direction, and the queue delivers at least once.
* V1 rewrites a customer document with exactly the fields it knows when a
  customer changes the address.
* The migration runs as a batch job while both versions serve traffic. It
  is killed by deploys and restarted; it must be safe to run any number of
  times.
* Rule: neither version may crash on, or lose data written by, the other.
  In particular, a V2 node saving a customer without changing its address
  or fee must leave the V1 fields exactly as they were (byte for byte):
  addresses do not always follow "street, postcode city" (foreign
  formats, odd spacing), and the V1 search index matches them literally.

# Warehouse writes

`TableWriter::write()` takes a save mode with the same meaning as Spark's
`DataFrameWriter.mode()`. Supported today: `error`, `append`, `overwrite`.
Rows without an `id` are rejected with `InvalidRow`; a rejected batch writes
nothing.

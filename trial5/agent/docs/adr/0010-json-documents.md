# ADR 0010: Store documents as JSON

Status: Accepted

`FileStore` persists JSON documents so that finance tooling can read them.
Integers must stay integers (no floats in stored amounts).

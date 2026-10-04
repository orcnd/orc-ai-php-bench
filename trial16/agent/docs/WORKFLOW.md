# Workflows

A workflow is a directed acyclic graph of steps. `run()` starts at the given
step; an edge's condition is evaluated when its source step has finished
(so it sees what that step wrote into the context). An edge without a
condition is always taken.

A step with several incoming edges is a join: it waits for all of them.
Every step runs at most once.

# Configuration Changes

This document describes all configuration file changes since the 3.0.0 release.
This in order to keep track of all changes that were made during the 3.x 
release cycle. 

This MAY help upgrades to a future 4.x release. Configuration changes during
the 3.x life cycle are NOT required. Any existing configuration file will keep
working!

## 3.0.1

As written in [#75](https://todo.sr.ht/~eduvpn/server/75), we make `oListenOn` 
accept multiple values as well, so in multi-node setups it can be used to 
specify the IP address used by each node explicitly.

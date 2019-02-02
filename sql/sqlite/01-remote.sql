CREATE TABLE chronicle_xsign_targets (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  name TEXT,
  url TEXT NOT NULL,
  clientid TEXT NOT NULL,
  publickey TEXT NOT NULL,
  policy TEXT NOT NULL,
  lastrun TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE chronicle_replication_sources (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  uniqueid TEXT,
  name TEXT,
  url TEXT,
  publickey TEXT
);

CREATE TABLE chronicle_replication_chain (
  id INTEGER PRIMARY KEY ASC AUTOINCREMENT,
  source INTEGER,
  data TEXT NOT NULL,
  prevhash TEXT NULL,
  currhash TEXT NOT NULL,
  hashstate TEXT NOT NULL,
  summaryhash TEXT NOT NULL,
  publickey TEXT NOT NULL,
  signature TEXT NOT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  replicated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (prevhash) REFERENCES chronicle_replication_chain(currhash),
  UNIQUE(source, prevhash)
);

CREATE INDEX chronicle_replication_chain_prevhash_idx ON chronicle_replication_chain(source, prevhash);
CREATE INDEX chronicle_replication_chain_currhash_idx ON chronicle_replication_chain(source, currhash);
CREATE INDEX chronicle_replication_chain_summaryhash_idx ON chronicle_replication_chain(source, summaryhash);

--
-- Created by SQL::Translator::Producer::PostgreSQL
-- Created on Fri Aug 21 12:44:53 2009
-- 

--
-- Table:
--
CREATE TABLE "" (
  "id" serial NOT NULL,
  "zone" text,
  "host" text,
  "type" text,
  "data" text,
  "ttl" bigint DEFAULT NULL,
  "mx_priority" text,
  "refresh" bigint DEFAULT NULL,
  "retry" bigint DEFAULT NULL,
  "expire" bigint DEFAULT NULL,
  "minimum" bigint DEFAULT NULL,
  "serial" bigint DEFAULT NULL,
  "resp_person" text,
  "owner" character varying(255) DEFAULT NULL
);
CREATE INDEX "id" on "" ("id");

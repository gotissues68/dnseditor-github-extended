--------------------------------------------------------
--  File created - Thursday-August-23-2012   
--------------------------------------------------------
--------------------------------------------------------
--  DDL for Table
--------------------------------------------------------

  CREATE TABLE "user"."table"
   (	"ID" NUMBER(10,0),
	"ZONE" VARCHAR2(255 CHAR),
	"HOST" VARCHAR2(255 CHAR),
	"TYPE" VARCHAR2(255 CHAR),
	"DATA" VARCHAR2(255 CHAR),
	"TTL" NUMBER(10,0),
	"MX_PRIORITY" VARCHAR2(255 CHAR),
	"REFRESH" NUMBER(10,0),
	"RETRY" NUMBER(10,0),
	"EXPIRE" NUMBER(10,0),
	"MINIMUM" NUMBER(10,0),
	"SERIAL" NUMBER(24,0),
	"RESP_PERSON" VARCHAR2(255 CHAR),
	"PRIMARY_NS" VARCHAR2(255 CHAR),
	"OWNER" VARCHAR2(255 CHAR)
   ) PCTFREE 10 PCTUSED 40 INITRANS 1 MAXTRANS 255 NOCOMPRESS LOGGING
  STORAGE(INITIAL 65536 NEXT 1048576 MINEXTENTS 1 MAXEXTENTS 2147483645
  PCTINCREASE 0 FREELISTS 1 FREELIST GROUPS 1 BUFFER_POOL DEFAULT)
  TABLESPACE "USERS" ;
REM INSERTING into user.table
SET DEFINE OFF;
--------------------------------------------------------
--  DDL for Index ID
--------------------------------------------------------

  CREATE INDEX "user"."ID" ON "user"."table" ("ID")
  PCTFREE 10 INITRANS 2 MAXTRANS 255 COMPUTE STATISTICS
  STORAGE(INITIAL 65536 NEXT 1048576 MINEXTENTS 1 MAXEXTENTS 2147483645
  PCTINCREASE 0 FREELISTS 1 FREELIST GROUPS 1 BUFFER_POOL DEFAULT)
  TABLESPACE "USERS" ;
--------------------------------------------------------
--  DDL for Index DNS_INDEX_3
--------------------------------------------------------

  CREATE INDEX "user"."DNS_INDEX_3" ON "user"."table" ("ZONE")
  PCTFREE 10 INITRANS 2 MAXTRANS 255 COMPUTE STATISTICS
  STORAGE(INITIAL 65536 NEXT 1048576 MINEXTENTS 1 MAXEXTENTS 2147483645
  PCTINCREASE 0 FREELISTS 1 FREELIST GROUPS 1 BUFFER_POOL DEFAULT)
  TABLESPACE "USERS" ;
--------------------------------------------------------
--  Constraints for Table table
--------------------------------------------------------

  ALTER TABLE "user"."table" MODIFY ("ID" NOT NULL ENABLE);

--------------------------------------------------------
--  DDL for Trigger table_ID_TRG
--------------------------------------------------------

  CREATE OR REPLACE TRIGGER "user"."table_ID_TRG" BEFORE INSERT ON table
FOR EACH ROW
DECLARE
v_newVal NUMBER(12) := 0;
v_incval NUMBER(12) := 0;
BEGIN
  IF INSERTING AND :new.id IS NULL THEN
    SELECT  table_id_SEQ.NEXTVAL INTO v_newVal FROM DUAL;
    -- If this is the first time this table have been inserted into (sequence == 1)
    IF v_newVal = 1 THEN
      --get the max indentity value from the table
      SELECT NVL(max(id),0) INTO v_newVal FROM table;
      v_newVal := v_newVal + 1;
      --set the sequence to that value
      LOOP
           EXIT WHEN v_incval>=v_newVal;
           SELECT table_id_SEQ.nextval INTO v_incval FROM dual;
      END LOOP;
    END IF;
    --used to emulate LAST_INSERT_ID()
    --mysql_utilities.identity := v_newVal; 
   -- assign the value from the sequence to emulate the identity column
   :new.id := v_newVal;
  END IF;
END;
/
ALTER TRIGGER "user"."table_ID_TRG" ENABLE;

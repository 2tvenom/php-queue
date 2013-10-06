CREATE SEQUENCE queue_tasks_id_seq;

CREATE TABLE "public"."queue_tasks" (
  "id"                              INTEGER DEFAULT nextval('queue_tasks_id_seq')             NOT NULL,
  "uniqid"                          CHARACTER VARYING(32) COLLATE "pg_catalog"."default"      NOT NULL UNIQUE,
  "type"                            INTEGER DEFAULT '1'                                       NOT NULL,
  "parent_id"                       CHARACTER VARYING(32) COLLATE "pg_catalog"."default" REFERENCES queue_tasks (uniqid) DEFAULT
    NULL ::
    CHARACTER VARYING,
  "subtasks_quantity"               INTEGER DEFAULT '0'                                       NOT NULL,
  "subtasks_quantity_not_performed" INTEGER DEFAULT '0'                                       NOT NULL,
  "subtasks_error"                  INTEGER DEFAULT '0'                                       NOT NULL,
  "exclusive"                       SMALLINT DEFAULT 0 :: SMALLINT                            NOT NULL,
  "task_group_id"                   INTEGER DEFAULT '1',
  "task_name"                       CHARACTER VARYING(255) COLLATE "pg_catalog"."default"     NOT NULL,
  "status"                          SMALLINT                                                  NOT NULL,
  "performer"                       CHARACTER VARYING(45) COLLATE "pg_catalog"."default" DEFAULT NULL ::
                                                                                                 CHARACTER VARYING,
  "request_data"                    TEXT COLLATE "pg_catalog"."default",
  "response_data"                   TEXT COLLATE "pg_catalog"."default",
  "execution_date"                  TIMESTAMP WITHOUT TIME ZONE,
  "callback"                        CHARACTER VARYING(255) COLLATE "pg_catalog"."default" DEFAULT NULL ::
                                                                                                  CHARACTER VARYING,
  "error_callback"                  CHARACTER VARYING(255) COLLATE "pg_catalog"."default" DEFAULT NULL ::
                                                                                                  CHARACTER VARYING,
  "settings"                        TEXT COLLATE "pg_catalog"."default",
  "priority"                        INTEGER,
  "create_date"                     TIMESTAMP WITHOUT TIME ZONE                               NOT NULL,
  "start_date"                      TIMESTAMP WITHOUT TIME ZONE,
  "done_date"                       TIMESTAMP WITHOUT TIME ZONE,
  PRIMARY KEY ("id", "uniqid"), CONSTRAINT "queue_tasksUnique" UNIQUE ("uniqid"));

CREATE INDEX "status" ON "public"."queue_tasks" USING BTREE ("status" ASC NULLS LAST);
CREATE INDEX "task_name" ON "public"."queue_tasks" USING BTREE ("task_name" ASC NULLS LAST);
CREATE INDEX "type" ON "public"."queue_tasks" USING BTREE ("type" ASC NULLS LAST);
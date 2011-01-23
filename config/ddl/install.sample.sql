USE [your_database];

CREATE TABLE `polyclip_attachments`(
	id					CHAR(36)			NOT NULL
	, model			VARCHAR(255)	NOT NULL
	, entity_id	CHAR(36) 			NOT NULL
	, alias     VARCHAR(255)	NOT NULL DEFAULT 'Attachment'
	, path			VARCHAR(255)	NOT NULL
	, url				VARCHAR(255)	NOT NULL
	, mimetype	VARCHAR(255)	NOT NULL DEFAULT 'text/unknown'
	, size			INT						NOT NULL DEFAULT -1
	, created		DATETIME			NOT NULL
	, modified	DATETIME			NOT NULL
	, PRIMARY KEY( id )
)
ENGINE=InnoDB;

CREATE TABLE polyclip_images(
	id					CHAR(36)			NOT NULL
	, model		VARCHAR(255)						NOT NULL
	, entity_id     CHAR(36)                                                NOT NULL
	, width			INT						NOT NULL DEFAULT -1
	, height		INT						NOT NULL DEFAULT -1
	, PRIMARY KEY( id )
)
ENGINE=InnoDB;

CREATE TABLE polyclip_thumbnails(
  id                        CHAR(36)              NOT NULL
  , polyclip_attachment_id  CHAR(36)              NOT NULL
  , alias                   VARCHAR(255)          NOT NULL
  , size                    INT                   NOT NULL DEFAULT -1
)
ENGINE=InnoDB;

GRANT ALL ON [your_database].binaries to [user] IDENTIFIED BY '[password]';

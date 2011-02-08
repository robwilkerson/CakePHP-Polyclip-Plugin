USE @DB_NAME@;

-- The shared physical file properties of a model attachment
-- Because this is a plugin, the path and URL are different values
CREATE TABLE polyclip_attachments(
	id					CHAR(36)			NOT NULL
	, model			VARCHAR(255)	NOT NULL
	, entity_id	CHAR(36) 			NOT NULL
	, alias     VARCHAR(255)  NOT NULL DEFAULT 'Attachment'
	, path      VARCHAR(255)  NOT NULL
	, url       VARCHAR(255)	NOT NULL
	, mimetype	VARCHAR(255)	NOT NULL DEFAULT 'text/unknown'
	, size			INT						NOT NULL DEFAULT -1
	, created		DATETIME			NOT NULL
	, modified	DATETIME			NOT NULL
	, PRIMARY KEY( id )
)
ENGINE=InnoDB;

-- Zero or more thumbnails may be created for a given attachment
CREATE TABLE polyclip_thumbnails(
	id									     CHAR(36)     NOT NULL
	, polyclip_attachment_id CHAR(36)     NOT NULL
	, alias                  VARCHAR(255) NOT NULL -- e.g. original, large, square, etc.
	-- , path                   VARCHAR(255) NOT NULL -- TODO: would like to do w/o this and derive basename from alias
	, size                   INT          NOT NULL DEFAULT -1
	, PRIMARY KEY( id )
	, FOREIGN KEY( polyclip_attachment_id )
		REFERENCES polyclip_attachments( id )
			ON UPDATE CASCADE
			ON DELETE CASCADE
)
ENGINE=InnoDB;

-- Stores additional details specific to images
CREATE TABLE polyclip_images(
	id          CHAR(36)     NOT NULL
	, model     VARCHAR(255) NOT NULL
	, entity_id CHAR(36)     NOT NULL
	, width     INT          NOT NULL DEFAULT -1
	, height    INT          NOT NULL DEFAULT -1
	, PRIMARY KEY( id )
)
ENGINE=InnoDB;

-- GRANT ALL ON YOUR_DATABASE.polyclip_attachments to USERNAME IDENTIFIED BY 'PASSWORD';
-- GRANT ALL ON YOUR_DATABASE.polyclip_thumbnails to USERNAME IDENTIFIED BY 'PASSWORD';
-- GRANT ALL ON YOUR_DATABASE.polyclip_images to USERNAME IDENTIFIED BY 'PASSWORD';

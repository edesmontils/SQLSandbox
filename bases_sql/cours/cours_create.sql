create table etudiants(
	noetu  varchar(6)      not null,
	nom     varchar(10)     not null,
	prenom  varchar(10)     not null,
	primary key (noetu)) ;

create table matieres(
	codemat        varchar(8)      not null primary key,
	titre           varchar(10),
	responsable     varchar(4),
	diplome         varchar(20));

create table notes(
	noe            varchar(6),
	codemat        varchar(8) ,
	noteex          numeric         check (noteex between 0 and 20),
	notecc          numeric         check (notecc between 0 and 20),
	primary key (noe, codemat),
	CONSTRAINT FK_noe       FOREIGN KEY (noe)       REFERENCES etudiants (noetu),
	CONSTRAINT FK_codemat   FOREIGN KEY (codemat)   REFERENCES matieres (codemat));


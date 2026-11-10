create table CLIENTS
(
    idcl NUMERIC
        primary key,
    nom  VARCHAR(20),
    pren VARCHAR(15),
    adr  VARCHAR(30),
    tel  VARCHAR(12)
);

INSERT INTO CLIENTS (idcl, nom, pren, adr, tel) VALUES (1, 'Moizeau', 'Isabelle', '1 rue des roses', '025031589');
INSERT INTO CLIENTS (idcl, nom, pren, adr, tel) VALUES (2, 'Guilet', 'Pierre', '1 rue des roses', '025031575');
INSERT INTO CLIENTS (idcl, nom, pren, adr, tel) VALUES (3, 'Dos Santos', 'Leonor', '17 boulevard de Launay', '065032089');
INSERT INTO CLIENTS (idcl, nom, pren, adr, tel) VALUES (4, 'Douillard', 'Lucie', '20 avenue wonderland', '085031566');

create table LIVRES
(
    refl   VARCHAR(10)
        primary key,
    titre  VARCHAR(50),
    auteur VARCHAR(20),
    genre  VARCHAR(15)
);

INSERT INTO LIVRES (refl, titre, auteur, genre) VALUES ('04B52', 'Fahrenheit 451', 'Ray Bradbury', 'science-fiction');
INSERT INTO LIVRES (refl, titre, auteur, genre) VALUES ('08E52', 'Le seigneur des anneaux', 'J. R. R. Tolkien', 'fantasy');
INSERT INTO LIVRES (refl, titre, auteur, genre) VALUES ('12V52', 'Le Guide du voyageur galactique', 'Douglas Adams', 'science-fiction');
INSERT INTO LIVRES (refl, titre, auteur, genre) VALUES ('AB52U', 'orgueil et prejuges', 'Jane Austen', 'romance');

create table ACHATS
(
    idcl      NUMERIC
        constraint fk_achats_clients
            references CLIENTS(idcl),
    refl      VARCHAR(10)
        constraint fk_achats_livres
            references LIVRES(refl),
    dateachat DATE,
    constraint pk_achats
        primary key (idcl, refl, dateachat)
);

INSERT INTO ACHATS (idcl, refl, dateachat) VALUES (1, '04B52', '2012-10-10');
INSERT INTO ACHATS (idcl, refl, dateachat) VALUES (1, '08E52', '2010-01-01');
INSERT INTO ACHATS (idcl, refl, dateachat) VALUES (1, '12V52', '2009-10-10');
INSERT INTO ACHATS (idcl, refl, dateachat) VALUES (2, '04B52', '2011-02-05');
INSERT INTO ACHATS (idcl, refl, dateachat) VALUES (3, '04B52', '2011-02-05');

create table AVIS
(
    idcl        NUMERIC
        constraint fk_avis_clients
            references CLIENTS(idcl),
    refl        VARCHAR(10)
        constraint fk_avis_livres
            references LIVRES(refl),
    note        NUMERIC(4, 2),
    commentaire VARCHAR(50),
    constraint pk_avis
        primary key (idcl, refl),
    constraint check_note_avis
        check (note BETWEEN 1 AND 20)
);

INSERT INTO AVIS (idcl, refl, note, commentaire) VALUES (1, '04B52', 18.00, 'tres juste.');
INSERT INTO AVIS (idcl, refl, note, commentaire) VALUES (1, '08E52', 15.00, 'pas mal.');
INSERT INTO AVIS (idcl, refl, note, commentaire) VALUES (1, '12V52', 20.00, 'imbattable.');
INSERT INTO AVIS (idcl, refl, note, commentaire) VALUES (2, '04B52', 20.00, 'genial.');
INSERT INTO AVIS (idcl, refl, note, commentaire) VALUES (3, '04B52', 20.00, null);
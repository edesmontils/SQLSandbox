create table Ville
(
    code     varchar(8)
        primary key,
    nomVille varchar(20),
    nb       int
);

INSERT INTO Ville (code, nomVille, nb) VALUES ('V75001', 'Paris', 2200000);
INSERT INTO Ville (code, nomVille, nb) VALUES ('V33000', 'Bordeaux', 250000);
INSERT INTO Ville (code, nomVille, nb) VALUES ('V13001', 'Marseille', 850000);
INSERT INTO Ville (code, nomVille, nb) VALUES ('V69001', 'Lyon', 500000);
INSERT INTO Ville (code, nomVille, nb) VALUES ('V31000', 'Toulouse', 475000);
INSERT INTO Ville (code, nomVille, nb) VALUES ('V44000', 'Nantes', 300000);
INSERT INTO Ville (code, nomVille, nb) VALUES ('V67000', 'Strasbourg', 275000);
INSERT INTO Ville (code, nomVille, nb) VALUES ('V34000', 'Montpellier', 275000);

create table Personne
(
    id     varchar(6)
        primary key,
    nom    varchar(20) not null,
    prenom varchar(20),
    age    numeric,
    code   varchar(8)
        constraint fkPersonne
            references Ville(code)
            on delete set null
);

INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P00001', 'Dupont', 'Jean', 34, 'V75001');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P00002', 'Durand', 'Marie', 28, 'V33000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P00003', 'Martin', 'Luc', 45, 'V13001');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P00004', 'Petit', 'Sophie', 30, 'V69001');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P10001', 'Leclerc', 'Emilie', 32, 'V31000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P10002', 'Bernard', 'Thomas', 29, 'V44000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P10003', 'Lefebvre', 'Clara', 26, 'V67000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P10004', 'Moreau', 'Alexandre', 40, 'V34000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20001', 'Fournier', 'Hugo', 37, 'V31000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20002', 'Roux', 'Alice', 25, 'V44000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20003', 'Girard', 'Jules', 29, 'V67000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20004', 'Lambert', 'Emma', 33, 'V34000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20005', 'Vasseur', 'Lucie', 42, 'V75001');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20006', 'Leroy', 'Benoît', 31, 'V33000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20007', 'Renard', 'Juliette', 28, 'V13001');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20008', 'Roy', 'Maxime', 36, 'V69001');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20009', 'Dent', 'Arthur', 52, 'V44000');
INSERT INTO Personne (id, nom, prenom, age, code) VALUES ('P20010', 'JARVIS', null, 2, null);

create table Entreprise
(
    ident    varchar(6)
        primary key,
    nom_ent  varchar(50),
    type_ent varchar(10),
    code     varchar(8)
        constraint fkEntreprise
            references Ville(code)
            on delete set null,
    id       varchar(6)
        references Personne(id)
);

INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E00001', 'Alpha Tech', 'Tech', 'V75001', 'P20005');
INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E00002', 'BHV Marais', 'Retail', 'V75001', null);
INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E00003', 'Bordeaux Wines', 'Agro', 'V33000', 'P20006');
INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E00004', 'Mars Networks', 'Tech', 'V13001', 'P20007');
INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E10001', 'Aerospace Dynamics', 'Aéro', 'V31000', 'P20001');
INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E10002', 'Digital Ocean', 'Tech', 'V44000', 'P10002');
INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E10003', 'BioHealth', 'Santé', 'V67000', 'P10003');
INSERT INTO Entreprise (ident, nom_ent, type_ent, code, id) VALUES ('E10004', 'Sunny Solar', 'Energie', 'V34000', 'P10004');

create table Travaille
(
    id              varchar(6)
        constraint fkTravailleId
            references Personne(id),
    ident           varchar(6)
        constraint fkTravailleIdent
            references Entreprise(ident)
            on delete cascade,
    salaire_mensuel numeric,
    constraint pkTravaille
        primary key (id, ident)
);

INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P00001', 'E00001', 3500);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P00002', 'E00003', 2800);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P00003', 'E00004', 3200);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P00004', 'E00001', 3800);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P10001', 'E10001', 4200);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P10002', 'E10002', 3600);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P10003', 'E10003', 3400);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P10004', 'E10004', 3700);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20001', 'E10001', 4200);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20002', 'E10002', 3100);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20003', 'E10003', 2900);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20004', 'E10004', 3600);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20005', 'E00001', 4000);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20006', 'E00003', 3000);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20007', 'E00004', 3400);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20008', 'E00001', 3900);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20004', 'E00001', 2300);
INSERT INTO Travaille (id, ident, salaire_mensuel) VALUES ('P20010', 'E00004', null);

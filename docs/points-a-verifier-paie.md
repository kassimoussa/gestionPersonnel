# Paie : points à vérifier avant tout versement réel

> **Les bulletins générés par l'application ne doivent pas servir à un vrai versement de
> salaire tant que les points ci-dessous n'ont pas été confirmés par un comptable.**

## Sources des taux et date de récupération

Tous les taux et tranches sont dans `config/paie.php` (aucune valeur en dur dans le code).
Ils ont été récupérés le **14 septembre 2026** depuis :

| Élément | Source | Valeurs retenues |
| --- | --- | --- |
| Cotisations CNSS | cnss.dj, page « Déclaration et versement des cotisations » | 21,7 % du brut (primes et indemnités incluses) : part salariale 6 % (retraite 4 % + soins 2 %), part patronale 15,7 % (prestations familiales 5,5 % + soins 5 % + accident de travail 1,2 % + retraite 4 %). Assiette minimale 20 000 FD. Aucun plafond identifié, aucun plafond appliqué. |
| IRPP | Code Général des Impôts, Direction Générale des Impôts, articles 14 à 16 (« Impôt sur les traitements et salaires ») | Tranches mensuelles progressives : 2 % jusqu'à 30 000 FD, 15 % de 30 000 à 50 000, 18 % de 50 000 à 150 000, 20 % de 150 000 à 600 000, 30 % au-delà. Base imposable = brut − part salariale CNSS. Contrat de moins d'un mois civil : 15 % forfaitaire sur la totalité du brut. |

Ni cnss.dj ni l'édition du CGI consultée n'affichent de date de mise à jour claire : une loi de
finances plus récente peut avoir modifié ces valeurs. À faire vérifier, puis corriger
`config/paie.php` si nécessaire.

## Point ouvert : article 14 du CGI (fraction inférieure à 5 000 FD)

L'article 14 indique que « toute fraction de revenu inférieure à 5 000 FD est négligée ».
Deux lectures sont possibles :

1. une **exonération** : aucun impôt en dessous de 5 000 FD de revenu ;
2. un **arrondi** de la base imposable à la tranche de 5 000 FD inférieure avant application
   du barème (par exemple 94 000 FD reste 94 000 FD, mais 94 800 FD deviendrait 90 000 FD).

**Aucune de ces deux interprétations n'est implémentée.** Le barème progressif est appliqué
sans arrondi ni exonération. Le point est signalé par un TODO en tête de
`app/Services/Paie/PaieCalculateur.php`. Il doit être tranché par un comptable, puis
implémenté dans `PaieCalculateur::irppProgressif()` (et couvert par un test dans
`tests/Unit/PaieCalculateurTest.php`).

## Exemple de référence (utilisé comme test)

Brut 100 000 FD en CDI : CNSS salarié 6 000, CNSS employeur 15 700, base imposable 94 000,
IRPP 600 + 3 000 + 7 920 = 11 520, net à payer 82 480, coût employeur 115 700.

## Autres hypothèses prises par l'application

- **Contrat court** : un contrat est « de moins d'un mois civil » si sa durée inclusive est
  strictement inférieure à un mois (du 1er au 30 septembre = un mois entier, donc barème
  progressif ; du 1er au 29 septembre = taux forfaitaire). Un contrat sans date de fin n'est
  jamais court. La règle est déduite des dates du contrat, jamais d'un champ saisi.
- **Contrat retenu** : celui en vigueur sur le mois de paie (le plus récent s'il y en a
  plusieurs). Pour le mois courant, c'est le contrat actuel de l'employé.
- **Salaire de base figé** : copié du contrat dans la fiche au moment de la génération. Un
  changement de contrat ultérieur ne modifie pas les fiches déjà générées.
- **Arrondis** : chaque montant est arrondi au centime ; les montants de l'exemple de
  référence tombent juste.
- **Base imposable** jamais négative (si le brut est sous le plancher CNSS, la déduction est
  plafonnée au brut).
- **Suppression d'un employé** : refusée dès qu'il possède au moins une fiche de paie,
  brouillon ou validée (clé étrangère `ON DELETE RESTRICT` et message explicite dans
  l'écran Employés). Une fiche de paie est un document comptable qui doit rester
  consultable après le départ : désactiver l'employé (champ « Employé actif ») au lieu de le
  supprimer. Les congés et pointages, eux, restent supprimés en cascade avec l'employé.

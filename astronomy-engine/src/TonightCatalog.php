<?php

declare(strict_types=1);

namespace AstronomyEngine;

/** Declarative, versioned catalogue for the portable tonight service. */
final class TonightCatalog
{
    public const STAR_VERSION = 'hipparcos-i-239-2026-07-25';

    /** @return list<array<string, int|float|string>> */
    public static function stars(): array
    {
        return [
            self::star('sirius','Sirio',32349,-1.44,101.28854105,-16.71314306,379.21,-546.01,-1223.08,'CMa','Can Mayor'),
            self::star('canopus','Canopo',30438,-0.62,95.98787763,-52.69571799,10.43,19.99,23.67,'Car','Quilla'),
            self::star('alpha_centauri','Alfa Centauri',71683,-0.01,219.92041034,-60.83514707,742.12,-3678.19,481.84,'Cen','Centauro'),
            self::star('arcturus','Arcturus',69673,-0.05,213.91811403,19.18726997,88.85,-1093.45,-1999.40,'Boo','Boyero'),
            self::star('vega','Vega',91262,0.03,279.23410832,38.78299311,128.93,201.02,287.46,'Lyr','Lira'),
            self::star('capella','Capella',24608,0.08,79.17206517,45.99902927,77.29,75.52,-427.13,'Aur','Auriga'),
            self::star('rigel','Rigel',24436,0.18,78.63446353,-8.20163919,4.22,1.87,-0.56,'Ori','Orión'),
            self::star('betelgeuse','Betelgeuse',27989,0.45,88.79287161,7.40703634,7.63,27.33,10.86,'Ori','Orión'),
            self::star('achernar','Achernar',7588,0.45,24.42813204,-57.23666007,22.68,88.02,-40.08,'Eri','Erídano'),
            self::star('hadar','Hadar',68702,0.61,210.95601898,-60.37297840,6.21,-33.96,-25.06,'Cen','Centauro'),
            self::star('altair','Altair',97649,0.76,297.69450860,8.86738491,194.44,536.82,385.54,'Aql','Águila'),
            self::star('acrux','Acrux',60718,0.77,186.64975585,-63.09905586,10.17,-35.37,-14.73,'Cru','Cruz del Sur'),
            self::star('antares','Antares',80763,1.06,247.35194804,-26.43194608,5.40,-10.16,-23.21,'Sco','Escorpio'),
            self::star('spica','Spica',65474,0.98,201.29835230,-11.16124491,12.44,-42.50,-31.73,'Vir','Virgo'),
            self::star('aldebaran','Aldebarán',21421,0.87,68.98000195,16.50976164,50.09,62.78,-189.36,'Tau','Tauro'),
            self::star('pollux','Pólux',37826,1.16,116.33068263,28.02631031,96.74,-625.69,-45.95,'Gem','Géminis'),
            self::star('regulus','Régulo',49669,1.36,152.09358075,11.96719513,42.09,-249.40,4.91,'Leo','Leo'),
            self::star('fomalhaut','Fomalhaut',113368,1.17,344.41177323,-29.62183701,130.08,329.22,-164.22,'PsA','Pez Austral'),
            self::star('deneb','Deneb',102098,1.25,310.35797270,45.28033423,1.01,1.56,1.55,'Cyg','Cisne'),
            self::star('mimosa','Mimosa',62434,1.25,191.93049537,-59.68873246,9.25,-48.24,-12.82,'Cru','Cruz del Sur'),
        ];
    }

    /** @return list<array<string, int|float|string>> */
    public static function clusters(): array
    {
        return [
            self::cluster('pleiades','Pléyades','Melotte','Mel 22',56.6008,24.1139,'Tau','Tauro'),
            self::cluster('hyades','Híades','Melotte','Mel 25',67.4471,16.9481,'Tau','Tauro'),
            self::cluster('m44','M44/Pesebre','Messier','M 44',130.0542,19.6211,'Cnc','Cáncer'),
        ];
    }

    /** @return array<string, int|float|string> */
    private static function star(string $id,string $name,int $hip,float $magnitude,float $ra,float $dec,float $parallax,float $pmRa,float $pmDec,string $abbr,string $constellation): array
    {
        return compact('id','name','hip','magnitude','ra','dec','parallax','pmRa','pmDec','abbr','constellation');
    }

    /** @return array<string, int|float|string> */
    private static function cluster(string $id,string $name,string $catalog,string $catalogId,float $ra,float $dec,string $abbr,string $constellation): array
    {
        return compact('id','name','catalog','catalogId','ra','dec','abbr','constellation');
    }
}

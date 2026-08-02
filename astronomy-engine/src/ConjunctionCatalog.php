<?php
declare(strict_types=1);
namespace AstronomyEngine;
final class ConjunctionCatalog
{
    /** @return list<ConjunctionTarget> */
    public static function targets():array{return[
        new ConjunctionTarget('mercury','planet'),new ConjunctionTarget('venus','planet'),new ConjunctionTarget('mars','planet'),new ConjunctionTarget('jupiter','planet'),new ConjunctionTarget('saturn','planet'),
        new ConjunctionTarget('aldebaran','star',68.98000195,16.50976164,62.78,-189.36),new ConjunctionTarget('elnath','star',81.57290804,28.60787346,23.28,-174.22),new ConjunctionTarget('alhena','star',99.42792641,16.39941482,-2.04,-66.92),new ConjunctionTarget('pollux','star',116.33068263,28.02631031,-625.69,-45.95),new ConjunctionTarget('regulus','star',152.09358075,11.96719513,-249.40,4.91),new ConjunctionTarget('zubenelgenubi','star',222.71990536,-16.04161047,-105.69,-69),new ConjunctionTarget('spica','star',201.29835230,-11.16124491,-42.50,-31.73),new ConjunctionTarget('antares','star',247.35194804,-26.43194608,-10.16,-23.21),new ConjunctionTarget('nunki','star',283.81631956,-26.29659428,13.87,-52.65),new ConjunctionTarget('deneb_algedi','star',326.75952199,-16.12656595,263.26,-296.23),
        new ConjunctionTarget('pleiades','open_cluster',56.6008,24.1139),new ConjunctionTarget('hyades','open_cluster',67.4471,16.9481),new ConjunctionTarget('m44','open_cluster',130.0542,19.6211),
    ];}
}

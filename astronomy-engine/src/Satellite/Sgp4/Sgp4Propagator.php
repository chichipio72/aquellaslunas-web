<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite\Sgp4;

use AstronomyEngine\Satellite\TemeState;
use AstronomyEngine\Satellite\Tle;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Vallado SGP4 near-earth branch using WGS-72 constants.
 *
 * Controlled PHP port of the MIT-licensed satellite-js implementation, itself
 * derived from Vallado's reference SGP4. See LICENSE-satellite-js.txt.
 */
final class Sgp4Propagator
{
    private const EARTH_RADIUS_KM = 6378.135;
    private const MU = 398600.8;
    private const XKE = 0.07436691613317342;
    private const VKM_PER_SECOND = self::EARTH_RADIUS_KM * self::XKE / 60.0;
    private const J2 = 0.001082616;
    private const J3 = -0.00000253881;
    private const J4 = -0.00000165597;
    private const J3_OVER_J2 = self::J3 / self::J2;
    private const TWO_THIRDS = 2.0 / 3.0;
    private const TWO_PI = 2.0 * M_PI;

    /** @var array<string,float|int> */
    private array $record;

    public function __construct(private readonly Tle $tle)
    {
        $this->record = $this->initialize($tle);
    }

    public function propagate(DateTimeImmutable $dateTime): TemeState
    {
        $utc = $dateTime->setTimezone(new DateTimeZone('UTC'));
        $minutes = ((float) $utc->format('U.u') - (float) $this->tle->epochUtc->format('U.u')) / 60.0;
        [$position, $velocity] = $this->calculate($minutes);
        return new TemeState($utc, $position, $velocity);
    }

    /** @return array<string,float|int> */
    private function initialize(Tle $tle): array
    {
        $record = [
            'bstar' => $tle->bstar, 'ecco' => $tle->eccentricity,
            'argpo' => $tle->argumentPerigeeRadians, 'inclo' => $tle->inclinationRadians,
            'mo' => $tle->meanAnomalyRadians, 'no' => $tle->meanMotionRadiansPerMinute,
            'nodeo' => $tle->rightAscensionAscendingNodeRadians,
            'isimp' => 0, 'aycof' => 0.0, 'con41' => 0.0, 'cc1' => 0.0,
            'cc4' => 0.0, 'cc5' => 0.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0,
            'delmo' => 0.0, 'eta' => 0.0, 'argpdot' => 0.0, 'omgcof' => 0.0,
            'sinmao' => 0.0, 't2cof' => 0.0, 't3cof' => 0.0, 't4cof' => 0.0,
            't5cof' => 0.0, 'x1mth2' => 0.0, 'x7thm1' => 0.0, 'mdot' => 0.0,
            'nodedot' => 0.0, 'xlcof' => 0.0, 'xmcof' => 0.0, 'nodecf' => 0.0,
        ];

        $eccsq = $record['ecco'] ** 2;
        $omeosq = 1.0 - $eccsq;
        if ($omeosq <= 0.0 || $record['no'] <= 0.0) {
            throw new InvalidArgumentException('SGP4 mean elements are outside valid limits.');
        }
        $rteosq = sqrt($omeosq);
        $cosio = cos($record['inclo']);
        $cosio2 = $cosio ** 2;
        $ak = (self::XKE / $record['no']) ** self::TWO_THIRDS;
        $d1 = 0.75 * self::J2 * (3.0 * $cosio2 - 1.0) / ($rteosq * $omeosq);
        $delta = $d1 / ($ak ** 2);
        $adel = $ak * (1.0 - $delta ** 2 - $delta * (1.0 / 3.0 + 134.0 * $delta ** 2 / 81.0));
        $delta = $d1 / ($adel ** 2);
        $record['no'] /= 1.0 + $delta;

        $periodMinutes = self::TWO_PI / $record['no'];
        if ($periodMinutes >= 225.0) {
            throw new InvalidArgumentException('This controlled SGP4 block currently supports near-earth TLEs only.');
        }

        $ao = (self::XKE / $record['no']) ** self::TWO_THIRDS;
        $sinio = sin($record['inclo']);
        $po = $ao * $omeosq;
        $con42 = 1.0 - 5.0 * $cosio2;
        $record['con41'] = -$con42 - 2.0 * $cosio2;
        $posq = $po ** 2;
        $rp = $ao * (1.0 - $record['ecco']);

        $ss = 78.0 / self::EARTH_RADIUS_KM + 1.0;
        $qzms2t = ((120.0 - 78.0) / self::EARTH_RADIUS_KM) ** 4;
        if ($rp < 220.0 / self::EARTH_RADIUS_KM + 1.0) $record['isimp'] = 1;
        $sfour = $ss;
        $qzms24 = $qzms2t;
        $perigee = ($rp - 1.0) * self::EARTH_RADIUS_KM;
        if ($perigee < 156.0) {
            $sfour = $perigee < 98.0 ? 20.0 : $perigee - 78.0;
            $qzms24 = ((120.0 - $sfour) / self::EARTH_RADIUS_KM) ** 4;
            $sfour = $sfour / self::EARTH_RADIUS_KM + 1.0;
        }
        $pinvsq = 1.0 / $posq;
        $tsi = 1.0 / ($ao - $sfour);
        $record['eta'] = $ao * $record['ecco'] * $tsi;
        $etasq = $record['eta'] ** 2;
        $eeta = $record['ecco'] * $record['eta'];
        $psisq = abs(1.0 - $etasq);
        $coef = $qzms24 * $tsi ** 4;
        $coef1 = $coef / $psisq ** 3.5;
        $cc2 = $coef1 * $record['no'] * (
            $ao * (1.0 + 1.5 * $etasq + $eeta * (4.0 + $etasq))
            + 0.375 * self::J2 * $tsi / $psisq * $record['con41'] * (8.0 + 3.0 * $etasq * (8.0 + $etasq))
        );
        $record['cc1'] = $record['bstar'] * $cc2;
        $cc3 = $record['ecco'] > 1.0e-4
            ? -2.0 * $coef * $tsi * self::J3_OVER_J2 * $record['no'] * $sinio / $record['ecco'] : 0.0;
        $record['x1mth2'] = 1.0 - $cosio2;
        $record['cc4'] = 2.0 * $record['no'] * $coef1 * $ao * $omeosq * (
            $record['eta'] * (2.0 + 0.5 * $etasq) + $record['ecco'] * (0.5 + 2.0 * $etasq)
            - self::J2 * $tsi / ($ao * $psisq) * (
                -3.0 * $record['con41'] * (1.0 - 2.0 * $eeta + $etasq * (1.5 - 0.5 * $eeta))
                + 0.75 * $record['x1mth2'] * (2.0 * $etasq - $eeta * (1.0 + $etasq)) * cos(2.0 * $record['argpo'])
            )
        );
        $record['cc5'] = 2.0 * $coef1 * $ao * $omeosq * (1.0 + 2.75 * ($etasq + $eeta) + $eeta * $etasq);
        $cosio4 = $cosio2 ** 2;
        $temp1 = 1.5 * self::J2 * $pinvsq * $record['no'];
        $temp2 = 0.5 * $temp1 * self::J2 * $pinvsq;
        $temp3 = -0.46875 * self::J4 * $pinvsq ** 2 * $record['no'];
        $record['mdot'] = $record['no'] + 0.5 * $temp1 * $rteosq * $record['con41']
            + 0.0625 * $temp2 * $rteosq * (13.0 - 78.0 * $cosio2 + 137.0 * $cosio4);
        $record['argpdot'] = -0.5 * $temp1 * $con42
            + 0.0625 * $temp2 * (7.0 - 114.0 * $cosio2 + 395.0 * $cosio4)
            + $temp3 * (3.0 - 36.0 * $cosio2 + 49.0 * $cosio4);
        $xhdot1 = -$temp1 * $cosio;
        $record['nodedot'] = $xhdot1
            + (0.5 * $temp2 * (4.0 - 19.0 * $cosio2) + 2.0 * $temp3 * (3.0 - 7.0 * $cosio2)) * $cosio;
        $record['omgcof'] = $record['bstar'] * $cc3 * cos($record['argpo']);
        if ($record['ecco'] > 1.0e-4) $record['xmcof'] = -self::TWO_THIRDS * $coef * $record['bstar'] / $eeta;
        $record['nodecf'] = 3.5 * $omeosq * $xhdot1 * $record['cc1'];
        $record['t2cof'] = 1.5 * $record['cc1'];
        $denominator = abs($cosio + 1.0) > 1.5e-12 ? 1.0 + $cosio : 1.5e-12;
        $record['xlcof'] = -0.25 * self::J3_OVER_J2 * $sinio * (3.0 + 5.0 * $cosio) / $denominator;
        $record['aycof'] = -0.5 * self::J3_OVER_J2 * $sinio;
        $record['delmo'] = (1.0 + $record['eta'] * cos($record['mo'])) ** 3;
        $record['sinmao'] = sin($record['mo']);
        $record['x7thm1'] = 7.0 * $cosio2 - 1.0;

        if ($record['isimp'] !== 1) {
            $cc1sq = $record['cc1'] ** 2;
            $record['d2'] = 4.0 * $ao * $tsi * $cc1sq;
            $temp = $record['d2'] * $tsi * $record['cc1'] / 3.0;
            $record['d3'] = (17.0 * $ao + $sfour) * $temp;
            $record['d4'] = 0.5 * $temp * $ao * $tsi * (221.0 * $ao + 31.0 * $sfour) * $record['cc1'];
            $record['t3cof'] = $record['d2'] + 2.0 * $cc1sq;
            $record['t4cof'] = 0.25 * (3.0 * $record['d3'] + $record['cc1'] * (12.0 * $record['d2'] + 10.0 * $cc1sq));
            $record['t5cof'] = 0.2 * (3.0 * $record['d4'] + 12.0 * $record['cc1'] * $record['d3']
                + 6.0 * $record['d2'] ** 2 + 15.0 * $cc1sq * (2.0 * $record['d2'] + $cc1sq));
        }
        return $record;
    }

    /** @return array{array{float,float,float},array{float,float,float}} */
    private function calculate(float $minutes): array
    {
        $r = $this->record;
        $xmdf = $r['mo'] + $r['mdot'] * $minutes;
        $argpdf = $r['argpo'] + $r['argpdot'] * $minutes;
        $nodedf = $r['nodeo'] + $r['nodedot'] * $minutes;
        $argpm = $argpdf;
        $mm = $xmdf;
        $t2 = $minutes ** 2;
        $nodem = $nodedf + $r['nodecf'] * $t2;
        $tempa = 1.0 - $r['cc1'] * $minutes;
        $tempe = $r['bstar'] * $r['cc4'] * $minutes;
        $templ = $r['t2cof'] * $t2;
        if ($r['isimp'] !== 1) {
            $delomg = $r['omgcof'] * $minutes;
            $delmtemp = 1.0 + $r['eta'] * cos($xmdf);
            $delm = $r['xmcof'] * ($delmtemp ** 3 - $r['delmo']);
            $temp = $delomg + $delm;
            $mm = $xmdf + $temp;
            $argpm = $argpdf - $temp;
            $t3 = $t2 * $minutes;
            $t4 = $t3 * $minutes;
            $tempa -= $r['d2'] * $t2 + $r['d3'] * $t3 + $r['d4'] * $t4;
            $tempe += $r['bstar'] * $r['cc5'] * (sin($mm) - $r['sinmao']);
            $templ += $r['t3cof'] * $t3 + $t4 * ($r['t4cof'] + $minutes * $r['t5cof']);
        }
        $nm = $r['no'];
        $em = $r['ecco'];
        if ($nm <= 0.0) throw new RuntimeException('SGP4 error 2: mean motion below zero.');
        $am = (self::XKE / $nm) ** self::TWO_THIRDS * $tempa ** 2;
        $nm = self::XKE / $am ** 1.5;
        $em -= $tempe;
        if ($em >= 1.0 || $em < -0.001) throw new RuntimeException('SGP4 error 1: eccentricity out of range.');
        $em = max($em, 1.0e-6);
        $mm += $r['no'] * $templ;
        $xlm = $mm + $argpm + $nodem;
        $nodem = fmod($nodem, self::TWO_PI);
        $argpm = fmod($argpm, self::TWO_PI);
        $xlm = fmod($xlm, self::TWO_PI);
        $mm = fmod($xlm - $argpm - $nodem, self::TWO_PI);

        $sinim = sin($r['inclo']);
        $cosim = cos($r['inclo']);
        $axnl = $em * cos($argpm);
        $temp = 1.0 / ($am * (1.0 - $em ** 2));
        $aynl = $em * sin($argpm) + $temp * $r['aycof'];
        $xl = $mm + $argpm + $nodem + $temp * $r['xlcof'] * $axnl;
        $u = fmod($xl - $nodem, self::TWO_PI);
        $eo1 = $u;
        $correction = 9999.9;
        for ($iteration = 0; abs($correction) >= 1.0e-12 && $iteration < 10; $iteration++) {
            $sineo1 = sin($eo1);
            $coseo1 = cos($eo1);
            $denominator = 1.0 - $coseo1 * $axnl - $sineo1 * $aynl;
            $correction = ($u - $aynl * $coseo1 + $axnl * $sineo1 - $eo1) / $denominator;
            $correction = max(-0.95, min(0.95, $correction));
            $eo1 += $correction;
        }
        $sineo1 = sin($eo1);
        $coseo1 = cos($eo1);
        $ecose = $axnl * $coseo1 + $aynl * $sineo1;
        $esine = $axnl * $sineo1 - $aynl * $coseo1;
        $el2 = $axnl ** 2 + $aynl ** 2;
        $pl = $am * (1.0 - $el2);
        if ($pl < 0.0) throw new RuntimeException('SGP4 error 4: semi-latus rectum below zero.');
        $rl = $am * (1.0 - $ecose);
        $rdotl = sqrt($am) * $esine / $rl;
        $rvdotl = sqrt($pl) / $rl;
        $betal = sqrt(1.0 - $el2);
        $temp = $esine / (1.0 + $betal);
        $sinu = $am / $rl * ($sineo1 - $aynl - $axnl * $temp);
        $cosu = $am / $rl * ($coseo1 - $axnl + $aynl * $temp);
        $su = atan2($sinu, $cosu);
        $sin2u = 2.0 * $cosu * $sinu;
        $cos2u = 1.0 - 2.0 * $sinu ** 2;
        $temp = 1.0 / $pl;
        $temp1 = 0.5 * self::J2 * $temp;
        $temp2 = $temp1 * $temp;
        $mrt = $rl * (1.0 - 1.5 * $temp2 * $betal * $r['con41']) + 0.5 * $temp1 * $r['x1mth2'] * $cos2u;
        if ($mrt < 1.0) throw new RuntimeException('SGP4 error 6: satellite has decayed.');
        $su -= 0.25 * $temp2 * $r['x7thm1'] * $sin2u;
        $xnode = $nodem + 1.5 * $temp2 * $cosim * $sin2u;
        $xinc = $r['inclo'] + 1.5 * $temp2 * $cosim * $sinim * $cos2u;
        $mvt = $rdotl - $nm * $temp1 * $r['x1mth2'] * $sin2u / self::XKE;
        $rvdot = $rvdotl + $nm * $temp1 * ($r['x1mth2'] * $cos2u + 1.5 * $r['con41']) / self::XKE;
        $sinsu = sin($su); $cossu = cos($su); $snod = sin($xnode); $cnod = cos($xnode);
        $sini = sin($xinc); $cosi = cos($xinc);
        $xmx = -$snod * $cosi; $xmy = $cnod * $cosi;
        $ux = $xmx * $sinsu + $cnod * $cossu;
        $uy = $xmy * $sinsu + $snod * $cossu;
        $uz = $sini * $sinsu;
        $vx = $xmx * $cossu - $cnod * $sinsu;
        $vy = $xmy * $cossu - $snod * $sinsu;
        $vz = $sini * $cossu;
        return [
            [$mrt * $ux * self::EARTH_RADIUS_KM, $mrt * $uy * self::EARTH_RADIUS_KM, $mrt * $uz * self::EARTH_RADIUS_KM],
            [($mvt * $ux + $rvdot * $vx) * self::VKM_PER_SECOND,
                ($mvt * $uy + $rvdot * $vy) * self::VKM_PER_SECOND,
                ($mvt * $uz + $rvdot * $vz) * self::VKM_PER_SECOND],
        ];
    }
}

<?php
/**
 * Decoupage d'un script SQL en instructions.
 *
 * Partage par scripts/migrate.php (DATA-01) et scripts/seed.php (DATA-08) :
 * PDO n'execute qu'une instruction a la fois, et un decoupage naif sur « ; »
 * casserait un declencheur ou une chaine contenant un point-virgule.
 */

declare(strict_types=1);

/**
 * Decoupe un script SQL en instructions.
 *
 * Gere les chaines, les commentaires et les changements de DELIMITER : un
 * declencheur contient des points-virgules dans son corps, un decoupage naif
 * le casserait en plein milieu.
 *
 * @return string[]
 */
function instructions(string $sql): array
{
    $instructions = [];
    $courante = '';
    $delimiteur = ';';
    $longueur = strlen($sql);
    $i = 0;

    while ($i < $longueur) {
        $reste = substr($sql, $i);

        // Changement de delimiteur (ligne entiere)
        if (($courante === '' || str_ends_with($courante, "\n")) && preg_match('/^DELIMITER[ \t]+(\S+)[ \t]*\r?\n?/i', $reste, $m)) {
            $delimiteur = $m[1];
            $i += strlen($m[0]);
            continue;
        }

        $caractere = $sql[$i];

        // Commentaires
        if ($courante === '' || str_ends_with(rtrim($courante, " \t"), "\n") || $courante === '') {
            if (str_starts_with($reste, '--') || str_starts_with($reste, '#')) {
                $fin = strpos($sql, "\n", $i);
                $i = $fin === false ? $longueur : $fin + 1;
                continue;
            }
        }
        if (str_starts_with($reste, '/*')) {
            $fin = strpos($sql, '*/', $i);
            $i = $fin === false ? $longueur : $fin + 2;
            continue;
        }

        // Chaines et identifiants proteges
        if ($caractere === "'" || $caractere === '"' || $caractere === '`') {
            $fermeture = $caractere;
            $courante .= $caractere;
            $i++;
            while ($i < $longueur) {
                $c = $sql[$i];
                $courante .= $c;
                $i++;
                if ($c === '\\' && $fermeture !== '`' && $i < $longueur) {
                    $courante .= $sql[$i];
                    $i++;
                    continue;
                }
                if ($c === $fermeture) {
                    break;
                }
            }
            continue;
        }

        // Fin d'instruction
        if (substr($sql, $i, strlen($delimiteur)) === $delimiteur) {
            $instruction = trim($courante);
            if ($instruction !== '') {
                $instructions[] = $instruction;
            }
            $courante = '';
            $i += strlen($delimiteur);
            continue;
        }

        $courante .= $caractere;
        $i++;
    }

    $instruction = trim($courante);
    if ($instruction !== '') {
        $instructions[] = $instruction;
    }

    return $instructions;
}

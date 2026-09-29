<?php
/**
 * Faux serveur SMTP pour les tests (aucun envoi reel).
 *
 *   php tests/outils/faux-smtp.php <port> <fichier de capture>
 *
 * Parle le SMTP de base (EHLO, AUTH LOGIN, MAIL, RCPT, DATA, QUIT), sans TLS.
 * Chaque session est ajoutee au fichier de capture en JSON (une ligne par
 * message) : identifiants recus, expediteur, destinataire, donnees brutes.
 * Accepte l'utilisateur « essai » avec le mot de passe « secret », refuse tout
 * autre.
 */

declare(strict_types=1);

$port = (int) ($argv[1] ?? 2525);
$capture = (string) ($argv[2] ?? sys_get_temp_dir() . '/faux-smtp.jsonl');
$serveur = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if (!$serveur) {
    fwrite(STDERR, "Port {$port} indisponible : {$errstr}\n");
    exit(1);
}
while ($client = @stream_socket_accept($serveur, -1)) {
    $session = ['utilisateur' => null, 'mot_de_passe' => null, 'de' => null, 'a' => null, 'donnees' => '', 'auth' => false];
    $ecrire = static fn (string $l) => fwrite($client, $l . "\r\n");
    $ecrire('220 faux-smtp pret');
    $etape = null;
    while (($ligne = fgets($client)) !== false) {
        $ligne = rtrim($ligne, "\r\n");
        if ($etape === 'utilisateur') {
            $session['utilisateur'] = base64_decode($ligne);
            $etape = 'mot_de_passe';
            $ecrire('334 UGFzc3dvcmQ6');
            continue;
        }
        if ($etape === 'mot_de_passe') {
            $session['mot_de_passe'] = base64_decode($ligne);
            $etape = null;
            $session['auth'] = $session['utilisateur'] === 'essai' && $session['mot_de_passe'] === 'secret';
            $ecrire($session['auth'] ? '235 authentifie' : '535 refuse');
            continue;
        }
        if ($etape === 'donnees') {
            if ($ligne === '.') {
                $etape = null;
                file_put_contents($capture, json_encode($session) . "\n", FILE_APPEND | LOCK_EX);
                $ecrire('250 message accepte');
                continue;
            }
            $session['donnees'] .= $ligne . "\r\n";
            continue;
        }
        $verbe = strtoupper(substr($ligne, 0, 4));
        match (true) {
            $verbe === 'EHLO' => fwrite($client, "250-faux-smtp\r\n250 AUTH LOGIN\r\n"),
            str_starts_with(strtoupper($ligne), 'AUTH LOGIN') => ($etape = 'utilisateur') && $ecrire('334 VXNlcm5hbWU6'),
            str_starts_with(strtoupper($ligne), 'MAIL FROM') => ($session['de'] = $ligne) && $ecrire($session['auth'] ? '250 ok' : '530 authentification requise'),
            str_starts_with(strtoupper($ligne), 'RCPT TO') => ($session['a'] = $ligne) && $ecrire('250 ok'),
            $verbe === 'DATA' => ($etape = 'donnees') && $ecrire('354 envoyez'),
            $verbe === 'QUIT' => $ecrire('221 au revoir'),
            default => $ecrire('500 commande inconnue'),
        };
        if ($verbe === 'QUIT') {
            break;
        }
    }
    fclose($client);
}

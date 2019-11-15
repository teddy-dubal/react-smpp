<?php

use Monolog\Logger;
use alexeevdv\React\Smpp\Server;
use Monolog\Handler\StreamHandler;
use alexeevdv\React\Smpp\Pdu\Unbind;
use alexeevdv\React\Smpp\Pdu\Factory;
use React\Socket\ConnectionInterface;
use alexeevdv\React\Smpp\Pdu\SubmitSm;
use alexeevdv\React\Smpp\Pdu\UnbindResp;
use alexeevdv\React\Smpp\Pdu\SubmitSmResp;
use alexeevdv\React\Smpp\Pdu\BindTransmitter;
use alexeevdv\React\Smpp\Proto\CommandStatus;
use alexeevdv\React\Smpp\Pdu\BindTransceiverResp;
use alexeevdv\React\Smpp\Pdu\BindTransmitterResp;

require_once 'vendor/autoload.php';

$loop       = React\EventLoop\Factory::create();
$host       = '127.0.0.1';
$port       = '2775';
$logger     = new Logger('react-smpp-server', [(new StreamHandler('php://stderr'))]);
$smppServer = new Server($host . ':' . $port, $loop, [], $logger);

$logger->info('Smpp Server started');
$logger->info('Host : ' . $host . ' - Port: ' . $port);
$smppServer->on('connection', function (ConnectionInterface $connection) use ($loop) {
    $session = ['id' => \uniqid(), 'auth' => false, 'id_smsc' => null, 'system_id' => null, 'password' => null];
    $connection->on('bind_transmitter', function (BindTransmitter $pdu) use ($connection, &$session) {
        $pduResp = new BindTransmitterResp();
        if ($pdu->getSystemId() == 'test' && $pdu->getPassword() == 'test') {
            $pduResp->setCommandStatus(CommandStatus::ESME_ROK);
            $session['auth']    = true;
            $session['id_smsc'] = time();
        } else {
            $pduResp->setCommandStatus(CommandStatus::ESME_RBINDFAIL);
        }
        $pduResp->setSequenceNumber($pdu->getSequenceNumber());
        $connection->emit('send', [$pduResp]);
    });
    $connection->on('bind_transceiver', function (BindTransceiver $pdu) use ($connection, &$session) {
        $pduResp = new BindTransceiverResp();
        if ($pdu->getSystemId() == 'test' && $pdu->getPassword() == 'test') {
            $pduResp->setCommandStatus(CommandStatus::ESME_ROK);
            $session['auth']    = true;
            $session['id_smsc'] = time();
        } else {
            $pduResp->setCommandStatus(CommandStatus::ESME_RBINDFAIL);
        }
        $pduResp->setSequenceNumber($pdu->getSequenceNumber());
        $connection->emit('send', [$pduResp]);

    });
    $connection->on('submit_sm', function (SubmitSm $pdu) use ($connection, &$session) {
        $pduResp = new SubmitSmResp;
        if (!$session['auth']) {
            $connection->close();
            return;
        }
        $idMessage = uniqid('id_msg_');
        $pduResp->setMessageId($idMessage)
            ->setSequenceNumber($pdu->getSequenceNumber());
        $connection->emit('send', [$pduResp]);

    });
    $connection->on('unbind', function (Unbind $pdu) use ($connection, &$session) {
        if (!$session['auth']) {
            $connection->close();
            return;
        }
        $pduResp = new UnbindResp();
        $pduResp->setSequenceNumber($pdu->getSequenceNumber());
        $connection->emit('send', [$pduResp]);
    });

    $connection->on('error', function ($error) use ($connection) {
        echo $error->getMessage();
        $connection->close();
    });
});

$loop->run();

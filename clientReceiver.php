#!/usr/bin/env php

<?php

use Monolog\Logger;
use OnlineCity\SMPP\SmppTag;
use alexeevdv\React\Smpp\Pdu\Pdu;
use Monolog\Handler\StreamHandler;
use alexeevdv\React\Smpp\Utils\Sms;
use alexeevdv\React\Smpp\Connection;
use alexeevdv\React\Smpp\Pdu\Unbind;
use alexeevdv\React\Smpp\Pdu\Factory;
use alexeevdv\React\Smpp\Pdu\SubmitSm;
use alexeevdv\React\Smpp\Pdu\DeliverSm;
use alexeevdv\React\Smpp\Proto\Address;
use alexeevdv\React\Smpp\Pdu\UnbindResp;
use alexeevdv\React\Smpp\Pdu\EnquireLink;
use alexeevdv\React\Smpp\Pdu\SubmitSmResp;
use alexeevdv\React\Smpp\Pdu\DeliverSmResp;
use alexeevdv\React\Smpp\Pdu\BindTransmitter;
use alexeevdv\React\Smpp\Pdu\EnquireLinkResp;
use alexeevdv\React\Smpp\Proto\CommandStatus;
use alexeevdv\React\Smpp\Pdu\BindReceiverResp;
use alexeevdv\React\Smpp\Pdu\BindTransmitterResp;
use alexeevdv\React\Smpp\Proto\DataCoding\Gsm0338;
use alexeevdv\React\Smpp\Proto\Contract\DataCoding;

require_once 'vendor/autoload.php';

$loop      = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop, ['timeout' => 5]);
$logger    = new Logger('react-smpp-client', [new StreamHandler('php://stderr')]);

$uri               = '127.0.0.1:2775';
$uri = '94.198.176.242:7662';

$credentials = ['id'=>'test','password'=>'test'];
$credentials = ['id' => 'sun', 'password' => '$unD1g'];

$connector->connect($uri)->then(function (React\Socket\ConnectionInterface $con) use ($logger,$loop,$credentials) {
    $session = ['id' => \uniqid(), 'auth' => false];
    $logger->info('SMPP client', $session);
    $connection = new Connection($con);
    $pdu        = new BindTransmitter();
    $pdu->setSystemId($credentials['id'])
        ->setPassword($credentials['password'])
        ->setSequenceNumber($connection->getNextSequenceNumber());
    $connection->write($pdu);

    $connection->on('data', function ($data) use ($connection) {
        $pduFactory = new Factory();
        try {
            $pdu = $pduFactory->createFromBuffer($data);
            $connection->emit('pdu', [$pdu]);
        } catch (\Exception $e) {
            return $connection->emit('error', [$e]);
        }
    });
    $connection->on('pdu', function (Pdu $pdu) use ($connection,$loop, &$session, $logger) {
        if ($pdu instanceof BindReceiverResp) {
            $logger->debug('<BindReceiverResp');
            if (in_array($pdu->getCommandStatus(), [CommandStatus::ESME_RBINDFAIL, CommandStatus::ESME_RUNKNOWNERR])) {
                $e = new \Exception('failed transmitter');
                return $connection->emit('error', [$e]);
            } 
            $logger->debug('', [
                'sequence'         => $pdu->getSequenceNumber(),
                'getCommandStatus' => $pdu->getCommandStatus(),
                'getCommandId'     => $pdu->getCommandId(),
                'getBody'          => $pdu->getBody(),
                'session'          => $session,
            ]);
            $logger->debug('BindReceiverResp>');
        }
        if ($pdu instanceof DeliverSm) {
            $logger->debug('<DeliverSm');
            $pduResp = new DeliverSmResp;
            $pduResp->setSequenceNumber($pdu->getSequenceNumber())
                ->setMessageId(time());
            $connection->write($pduResp);
            $logger->debug('', [
                'sequence'         => $pdu->getSequenceNumber(),
                'getCommandStatus' => $pdu->getCommandStatus(),
                'getCommandId'     => $pdu->getCommandId(),
                'getBody'          => $pdu->getBody(),
                'session'          => $session,
            ]);
            $logger->debug('DeliverSm>');
            $connection->close();
        }
        if ($pdu instanceof EnquireLink) {
            $logger->debug('<EnquireLink');
            $pduResp = new EnquireLinkResp;
            $pduResp->setSequenceNumber($pdu->getSequenceNumber());
            $connection->write($pduResp);
            $logger->debug('', [
                'sequence'         => $pdu->getSequenceNumber(),
                'getCommandStatus' => $pdu->getCommandStatus(),
                'getCommandId'     => $pdu->getCommandId(),
                'session'          => $session,
            ]);
            $logger->debug('EnquireLink>');
        }
        if ($pdu instanceof UnbindResp) {
            $logger->debug('<UnbindResp');
            $connection->end();
            $logger->debug('UnbindResp>');
        }
    });
    $connection->on('error', function ($e) use ($connection, $logger) {
        $logger->debug('<ERROR');
        $pdu = (new Unbind())->setSequenceNumber($connection->getNextSequenceNumber());
        $connection->write($pdu);
        $connection->end();
        $logger->debug('ERROR>');
    });
});

$loop->run();

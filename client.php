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
use alexeevdv\React\Smpp\Proto\Address;
use alexeevdv\React\Smpp\Pdu\UnbindResp;
use alexeevdv\React\Smpp\Pdu\SubmitSmResp;
use alexeevdv\React\Smpp\Pdu\BindTransmitter;
use alexeevdv\React\Smpp\Proto\CommandStatus;
use alexeevdv\React\Smpp\Pdu\BindTransmitterResp;
use alexeevdv\React\Smpp\Proto\DataCoding\Gsm0338;
use alexeevdv\React\Smpp\Proto\Contract\DataCoding;

require_once 'vendor/autoload.php';

$loop      = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop, ['timeout' => 5]);
$logger    = new Logger('react-smpp-client', [new StreamHandler('php://stderr')]);

$uri               = '127.0.0.1:2775';
// $uri               = '94.198.176.242:7662';

$credentials = ['id'=>'sun','password'=>'$unD1g'];
$credentials = ['id'=>'test','password'=>'test'];

$connector->connect($uri)->then(function (React\Socket\ConnectionInterface $con) use ($logger,$loop,$credentials) {
    $session = ['id' => \uniqid(), 'auth' => false, 'id_smsc' => null, 'system_id' => null, 'password'];
    $logger->info('SMPP client', $session);
    $connection = new Connection($con);
    $pdu        = new BindTransmitter();
    // $pdu            = new BindTransceiver();
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
        if ($pdu instanceof BindTransmitterResp) {
            $logger->debug('<BindTransmitterResp');
            if (in_array($pdu->getCommandStatus(), [CommandStatus::ESME_RBINDFAIL, CommandStatus::ESME_RUNKNOWNERR])) {
                $e = new \Exception('failed transmitter');
                return $connection->emit('error', [$e]);
            }
            if (CommandStatus::ESME_ROK == $pdu->getCommandStatus()) {
                $message = 'H€llo world é à ù ' . time();
                $message = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer venenatis vehicula odio, non pharetra ex varius facilisis. Donec consectetur, velit non nullam.deux sms';
                $msg = (new Gsm0338())->encode($message);
                try {
                    $sms = new Sms();
                    $sms->setSourceAddress(new Address(Address::TON_UNKNOWN, Address::NPI_UNKNOWN, 93134))
                    ->setDestinationAddress(new Address(Address::TON_INTERNATIONAL, Address::NPI_ISDN, 590690766186))
                    ->setShortMessage($msg)
                    ->setSequenceNumber($connection->getNextSequenceNumber())
                    ->setConnection($connection)
                    ->setLoop($loop)
                    ->send();
                } catch (\Throwable $th) {
                    return $connection->emit('error', [$th]);
                }
                $logger->debug('', [
                    'sequence'     => $pdu->getSequenceNumber(),
                    'getCommandId' => $pdu->getCommandId(),
                    'getBody'      => $pdu->getBody(),
                    'session'      => $session,
                ]);

            }
            $logger->debug('BindTransmitterResp>');
        }
        if ($pdu instanceof SubmitSmResp) {
            $logger->debug('<SubmitSmResp');
            if (in_array($pdu->getCommandStatus(), [CommandStatus::ESME_RSUBMITFAIL, CommandStatus::ESME_RUNKNOWNERR])) {
                $e = new \Exception('failed submitsm');
                $connection->emit('error', [$e]);
            } else {
                $connection->write((new Unbind())->setSequenceNumber($connection->getNextSequenceNumber()));
                $connection->end();
            }
            $logger->debug('', [
                'sequence'         => $pdu->getSequenceNumber(),
                'getCommandStatus' => $pdu->getCommandStatus(),
                'getCommandId'     => $pdu->getCommandId(),
                'getMessageId'     => $pdu->getMessageId(),
                'getBody'          => $pdu->getBody(),
                'session'          => $session,
            ]);
            $logger->debug('SubmitSmResp>');
        }
        if ($pdu instanceof UnbindResp) {
            $logger->debug('<UnbindResp');
            $connection->end();
            $logger->debug('UnbindResp>');
        }
    });
    $connection->on('error', function ($e) use ($connection, $logger) {
        $logger->debug('<ERROR');
        //On logue les erreurs
        var_dump(hex2bin($e->getMessage()));
        $pdu = new Unbind();
        $connection->write($pdu);
        $connection->end();
        $logger->debug('ERROR>');
    });
});

$loop->run();

#!/usr/bin/env php

<?php

use alexeevdv\React\Smpp\Connection;
use alexeevdv\React\Smpp\Pdu\BindTransceiver;
use alexeevdv\React\Smpp\Pdu\BindTransceiverResp;
use alexeevdv\React\Smpp\Pdu\Factory;
use alexeevdv\React\Smpp\Pdu\Pdu;
use alexeevdv\React\Smpp\Pdu\SubmitSmResp;
use alexeevdv\React\Smpp\Pdu\Unbind;
use alexeevdv\React\Smpp\Pdu\UnbindResp;
use alexeevdv\React\Smpp\Proto\Address;
use alexeevdv\React\Smpp\Proto\CommandStatus;
use alexeevdv\React\Smpp\Proto\DataCoding\Gsm0338;
use alexeevdv\React\Smpp\Utils\Sms;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once 'vendor/autoload.php';

$loop      = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop, ['timeout' => 20]);
$logger    = new Logger('react-smpp-client', [new StreamHandler('php://stderr')]);

$uri = '127.0.0.1:2775';
$uri = ':';

$credentials = ['id' => 'test', 'password' => 'test'];
$credentials = ['id' => '', 'password' => ''];

$connector->connect($uri)->then(function (React\Socket\ConnectionInterface $con) use ($logger, $loop, $credentials) {
    $session = ['id' => \uniqid(), 'auth' => false, 'id_smsc' => null, 'system_id' => null, 'password'];
    $logger->info('SMPP client', $session);
    $connection = new Connection($con);
    $pdu        = new BindTransceiver();
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
    $connection->on('pdu', function (Pdu $pdu) use ($connection, $loop, &$session, $logger) {
        if ($pdu instanceof BindTransceiverResp) {
            $logger->debug('<BindTransceiverResp');
            if (in_array($pdu->getCommandStatus(), [CommandStatus::ESME_RBINDFAIL, CommandStatus::ESME_RUNKNOWNERR])) {
                $e = new \Exception('failed transmitter');
                return $connection->emit('error', [$e]);
            }
            if (CommandStatus::ESME_ROK == $pdu->getCommandStatus()) {
                $message = 'H€llo world é à ù ' . time();
                $message = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer venenatis vehicula odio, non pharetra ex varius facilisis. Donec consectetur, velit non nullam.deux sms';
                // $message .= 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer venenatis vehicula odio, non pharetra ex varius facilisis. Donec consectetur, velit non nullam.deux sms';
                $msg = (new Gsm0338())->encode($message);
                try {
                    $sms = new Sms();
                    $sms->setSourceAddress(new Address(Address::TON_UNKNOWN, Address::NPI_UNKNOWN, 11111))
                        ->setDestinationAddress(new Address(Address::TON_INTERNATIONAL, Address::NPI_ISDN, 590690111111))
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
            $logger->debug('BindTransceiverResp>');
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
        $pdu = (new Unbind())->setSequenceNumber($connection->getNextSequenceNumber());
        $connection->write($pdu);
        $connection->end();
        $logger->debug('ERROR>');
    });
});

$loop->run();

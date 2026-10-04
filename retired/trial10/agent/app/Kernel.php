<?php
declare(strict_types=1);
namespace App;

use App\Accounts\UserRepository;
use App\Archive\AdressArchive;
use App\Net\DnsResolver;
use App\Net\SystemDnsResolver;
use App\Newsletter\SubscriberList;

/** Composition root for the web front controller. */
final class Kernel
{
    public DnsResolver $dns;
    public AdressArchive $archive;
    public UserRepository $users;
    public SubscriberList $subscribers;

    public function __construct(?DnsResolver $dns = null)
    {
        $this->dns = $dns ?? new SystemDnsResolver();
        $this->archive = new AdressArchive();
        $this->users = new UserRepository();
        $this->subscribers = new SubscriberList();
    }
}

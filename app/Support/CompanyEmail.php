<?php

namespace App\Support;

/**
 * The single definition of a company address, shared by registration,
 * profile email changes and Google sign-in.
 */
final class CompanyEmail
{
    /**
     * @return list<string> lowercase domains, e.g. ['leasoft.org']
     */
    public static function domains(): array
    {
        return config('auth.company_email_domains', []);
    }

    public static function isCompanyDomain(?string $domain): bool
    {
        return is_string($domain) && in_array(strtolower($domain), self::domains(), true);
    }

    public static function isCompany(?string $email): bool
    {
        return is_string($email) && preg_match(self::pattern(), $email) === 1;
    }

    /**
     * Validation rule; pair it with an `email.regex` message.
     */
    public static function rule(): string
    {
        return 'regex:'.self::pattern();
    }

    /**
     * Human-readable list for messages, e.g. "@leasoft.org".
     */
    public static function label(): string
    {
        return implode(', ', array_map(fn (string $domain) => '@'.$domain, self::domains()));
    }

    private static function pattern(): string
    {
        $domains = array_map(fn (string $domain) => preg_quote($domain, '/'), self::domains());

        // No configured domains means no address qualifies
        return $domains === []
            ? '/(?!)/'
            : '/^[^@\s]+@('.implode('|', $domains).')$/i';
    }
}

<?php

/**
 * @package Am_Utils 
 */
class Am_Validate
{
    static function empty_or_email($email)
    {
        if ($email == "") return true;
        return self::email($email);
    }
    static function email($email) {
        #characters allowed on name: 0-9a-Z-._ on host: 0-9a-Z-. on between: @
        if (!preg_match('/^[0-9a-zA-Z\.\-\_+]+\@[0-9a-zA-Z\.\-]+$/', $email))
            return false;
        #must start or end with alpha or num
        if ( preg_match('/^[^0-9a-zA-Z]|[^0-9a-zA-Z]$/', $email))
            return false;
        #name must end with alpha or num or _ or -
        if (!preg_match('/([0-9a-zA-Z_\-]{1})\@./',$email) )
            return false;
        #host must start with alpha or num
        if (!preg_match('/.\@([0-9a-zA-Z_]{1})/',$email) )
            return false;
        #pair .- or -. or -- or .. not allowed
        #if ( preg_match('/.\.\-.|.\-\..|.\.\..|.\-\-./',$email) )
        #    return false;
        #host must end with '.' plus 2-6 alpha for TopLevelDomain
        if (!preg_match('/\.([a-zA-Z]{2,6})$/',$email) )
            return false;
        return true;
    }
}
<?
class HTTPAuth
{
    public static function isSigned()
    {
        return (false and isset($_SERVER['HTTP_AUTHORIZATION']))
            || isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    }

    // TODO: Digest
    // TODO: http://www.php.net/manual/de/features.http-auth.php#106285
    public static function verify()
    {
        $user_id = false;

        if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];
            $check = StudipAuthAbstract::CheckAuthentication($username, $password);
            if (!$check['uid'] || $check['uid'] == 'nobody') {
                throw new Exception(trim(strip_tags($check['error'])), 401);
            }
            $user_id = $check['uid'];
        }
        return $user_id;
    }
}
<?php

namespace oauth\gitlab;

class gitlab
{
    const AUTHORIZE_URL = 'https://gitlab.com/oauth/authorize';
    const TOKEN_URL = 'https://gitlab.com/oauth/token';
    const USERINFO_URL = 'https://gitlab.com/api/v4/user';

    public function meta()
    {
        return [
            'name'        => 'GitLab 登录',
            'description' => '使用 GitLab 账号登录',
            'author'      => 'xkatld',
            'logo_url'    => 'gitlab.png',
        ];
    }

    public function config()
    {
        return [
            'App ID (Client ID)' => [
                'type' => 'text',
                'name' => 'clientId',
                'desc' => '在GitLab应用中创建应用后获得的Application ID (Client ID)'
            ],
            'App Secret (Client Secret)' => [
                'type' => 'text',
                'name' => 'clientSecret',
                'desc' => '在GitLab应用中创建应用后获得的Secret (Client Secret)'
            ],
        ];
    }

    public function url($params)
    {
        if (empty($params['clientId']) || empty($params['callback'])) {
            throw new \Exception("GitLab OAuth 配置错误: 缺少 App ID 或回调地址.");
        }

        $clientId = $params['clientId'];
        $redirectUri = $params['callback'];
        $scopes = 'read_user openid profile email';

        $state = md5(uniqid(rand(), true));

        $query = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $scopes,
            'state'         => $state,
        ]);

        return self::AUTHORIZE_URL . '?' . $query;
    }

    public function callback($params)
    {
        if (empty($params['clientId']) || empty($params['clientSecret']) || empty($params['code'])) {
             throw new \Exception("GitLab OAuth 回调错误: 缺少 App ID, App Secret, 或授权码.");
        }

        $clientId = $params['clientId'];
        $clientSecret = $params['clientSecret'];
        $redirectUri = $params['callback'];

        $code = $params['code'];
        $state = isset($params['state']) ? $params['state'] : null;

        $tokenUrl = self::TOKEN_URL;
        $tokenParams = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 GitLab 访问令牌失败. HTTP Code: " . $httpCode . ", 错误: " . ($error ?: '未知错误'));
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? '未知错误';
            throw new \Exception("GitLab 访问令牌响应中未找到令牌: " . $errorMsg);
        }

        $accessToken = $tokenData['access_token'];

        $userInfoUrl = self::USERINFO_URL;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 GitLab 用户信息失败. HTTP Code: " . $httpCode . ", 错误: " . ($error ?: '未知错误'));
        }

        $userData = json_decode($response, true);

        if (!isset($userData['id'])) {
             $errorMsg = $userData['message'] ?? '未知错误';
             throw new \Exception("GitLab 用户信息响应中未找到用户ID: " . $errorMsg);
        }

        $openid = (string) $userData['id'];

        $userInfo = [
            'username' => $userData['username'] ?? $userData['name'] ?? 'GitLab 用户',
            'sex'      => '',
            'province' => '',
            'city'     => '',
            'avatar'   => $userData['avatar_url'] ?? '',
        ];

        return [
            'openid'       => $openid,
            'data'         => $userInfo,
            'callbackBind' => 'all',
        ];
    }
}

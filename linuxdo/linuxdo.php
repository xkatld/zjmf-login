<?php

namespace oauth\linuxdo;

class linuxdo
{
    public function meta()
    {
        return [
            'name'        => 'Linux.do登录',
            'description' => '使用Linux.do账号登录您的网站',
            'author'      => 'xkatld',
            'logo_url'    => 'linuxdo.svg',
        ];
    }

    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '从Linux.do管理员处获取的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '从Linux.do管理员处获取的Client Secret'
            ],
        ];
    }

    public function url($params)
    {
        if (empty($params['client_id']) || empty($params['callback'])) {
            throw new \Exception("Linux.do OAuth 配置错误: 缺少 Client ID 或回调地址.");
        }

        $clientId = $params['client_id'];
        $redirectUri = $params['callback'];
        $scope = 'read';

        $authBaseUrl = 'https://linux.do/oauth2/authorize';

        $authUrl = $authBaseUrl . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
        ]);

        return $authUrl;
    }

    public function callback($params)
    {
        if (empty($params['client_id']) || empty($params['client_secret']) || empty($params['code'])) {
             throw new \Exception("Linux.do OAuth 回调错误: 缺少 Client ID, Client Secret, 或授权码.");
        }

        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $redirectUri = $params['callback'];
        $code = $params['code'];

        $tokenUrl = 'https://linux.do/oauth2/token';

        $tokenParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
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
            throw new \Exception("获取 Linux.do 访问令牌失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
             $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? '未知错误';
             throw new \Exception("Linux.do 访问令牌响应中未找到令牌: " . $errorMsg);
        }

        $accessToken = $tokenData['access_token'];

        $userInfoUrl = 'https://linux.do/oauth2/user';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl . '?access_token=' . $accessToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 Linux.do 用户信息失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $userData = json_decode($response, true);

        if (!isset($userData['user']['id'])) {
             $errorMsg = $userData['message'] ?? '未知错误';
             throw new \Exception("Linux.do 用户信息响应中未找到用户ID: " . $errorMsg);
        }

        $openid = (string) $userData['user']['id'];
        $userInfo = [
            'username' => $userData['user']['username'] ?? $userData['user']['name'] ?? 'Linux.do 用户',
            'sex'      => null,
            'province' => null,
            'city'     => null,
            'avatar'   => $userData['user']['avatar_template'] ? 'https://linux.do' . str_replace('{size}', '120', $userData['user']['avatar_template']) : null,
        ];

        $callbackBind = 'all';

        return [
            'openid'       => $openid,
            'data'         => $userInfo,
            'callbackBind' => $callbackBind,
        ];
    }
}

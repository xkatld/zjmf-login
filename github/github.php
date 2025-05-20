<?php

namespace oauth\github;

class github
{
    public function meta()
    {
        return [
            'name'        => 'GitHub登录',
            'description' => '使用GitHub账号登录您的网站',
            'author'      => 'xkatld', 
            'logo_url'    => 'github.svg',
        ];
    }

    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '从GitHub获取的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '从GitHub获取的Client Secret'
            ],
        ];
    }

    public function url($params)
    {
        if (empty($params['client_id']) || empty($params['callback'])) {
            throw new \Exception("GitHub OAuth 配置错误: 缺少 Client ID 或回调地址.");
        }

        $clientId = $params['client_id'];
        $redirectUri = $params['callback'];
        $scope = 'read:user';

        $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            // 'state' => $params['state'] ?? 'your_generated_state', // 建议加入state参数，防止CSRF攻击
        ]);

        return $authUrl;
    }

    public function callback($params)
    {
        if (empty($params['client_id']) || empty($params['client_secret']) || empty($params['code'])) {
             throw new \Exception("GitHub OAuth 回调错误: 缺少 Client ID, Client Secret, 或授权码.");
        }

        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $redirectUri = $params['callback'];
        $code = $params['code'];

        $tokenUrl = 'https://github.com/login/oauth/access_token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri, // 获取令牌时必须包含redirect_uri
        ];

        $headers = ['Accept: application/json'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 GitHub 访问令牌失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? '未知错误';
            throw new \Exception("GitHub 访问令牌响应中未找到令牌: " . $errorMsg);
        }

        $accessToken = $tokenData['access_token'];

        $userInfoUrl = 'https://api.github.com/user';
        $headers = [
            'Authorization: token ' . $accessToken,
            'User-Agent: ' . ($this->meta()['author'] ?? 'FinancialSystemOAuthPlugin'),
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 GitHub 用户信息失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $userData = json_decode($response, true);

        if (!isset($userData['id'])) {
             $errorMsg = $userData['message'] ?? '未知错误';
             throw new \Exception("GitHub 用户信息响应中未找到用户ID: " . $errorMsg);
        }

        $openid = (string) $userData['id'];
        $userInfo = [
            'username' => $userData['login'] ?? $userData['name'] ?? 'GitHub 用户',
            'sex'      => null,
            'province' => $userData['location'] ?? null,
            'city'     => null,
            'avatar'   => $userData['avatar_url'] ?? null,
        ];

        $callbackBind = 'all';

        return [
            'openid'       => $openid,
            'data'         => $userInfo,
            'callbackBind' => $callbackBind,
        ];
    }
}

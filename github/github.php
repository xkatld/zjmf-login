<?php

namespace oauth\github;

class github
{
    public function meta()
    {
        return [
            'name'        => 'GitHub登录',
            'description' => '使用GitHub账号登录',
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
                'desc' => 'GitHub OAuth应用的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => 'GitHub OAuth应用的Client Secret'
            ],
        ];
    }

    public function url($params)
    {
        $clientId = $params['client_id'];
        $redirectUri = urlencode($params['callback']);
        $state = md5(uniqid(rand(), true));

        $authUrl = "https://github.com/login/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&scope=user&state={$state}";

        return $authUrl;
    }

    public function callback($params)
    {
        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $code = $params['code'] ?? '';

        if (empty($code)) {
            throw new \Exception('授权失败，请重试');
        }

        // 获取访问令牌
        $tokenUrl = "https://github.com/login/oauth/access_token";
        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            throw new \Exception('获取访问令牌失败');
        }

        $accessToken = $tokenData['access_token'];

        // 获取用户信息
        $userInfoUrl = "https://api.github.com/user";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $accessToken,
            'User-Agent: YourAppName'
        ]);
        $userResponse = curl_exec($ch);
        curl_close($ch);

        $userData = json_decode($userResponse, true);

        if (!isset($userData['id'])) {
            throw new \Exception('获取用户信息失败');
        }

        return [
            'openid' => $userData['id'],
            'data' => [
                'username' => $userData['login'],
                'sex' => '', // GitHub不提供性别信息
                'province' => '', // GitHub不提供省份信息
                'city' => $userData['location'] ?? '',
                'avatar' => $userData['avatar_url'],
            ],
            'callbackBind' => 'all',
        ];
    }
}

<?php


namespace Adaojunior\SocialGrant\Tests;

use DateInterval;
use Adaojunior\SocialGrant\Tests\Stubs\AccessTokenEntity;
use Adaojunior\SocialGrant\Tests\Stubs\ClientEntity;
use Adaojunior\SocialGrant\Tests\Stubs\RefreshTokenEntity;
use Adaojunior\SocialGrant\Tests\Stubs\ScopeEntity;
use Adaojunior\SocialGrant\Tests\Stubs\StubResponseType;
use Adaojunior\SocialGrant\Tests\Stubs\UserEntity;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Adaojunior\Passport\SocialGrant;
use Adaojunior\Passport\SocialGrantUserResolver;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Zend\Diactoros\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;

class SocialGrantTest extends TestCase
{
    const DEFAULT_SCOPE = 'basic';

    public function testGetIdentifier()
    {
        $grant = new SocialGrant(
            $this->getMockBuilder(SocialGrantUserResolver::class)->getMock(),
            $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock()
        );

        $this->assertEquals('social', $grant->getIdentifier());
    }

    public function testRespondToRequest()
    {
        $client = new ClientEntity();
        $clientRepositoryMock = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepositoryMock->method('getClientEntity')->willReturn($client);
        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();
        $accessTokenRepositoryMock->method('getNewToken')->willReturn(new AccessTokenEntity());
        $accessTokenRepositoryMock->method('persistNewAccessToken')->willReturnSelf();
        $resolverMock = $this->getMockBuilder(SocialGrantUserResolver::class)->getMock();
        $userEntity = new UserEntity();
        $resolverMock->method('resolve')->willReturn($userEntity);
        $refreshTokenRepositoryMock = $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock();
        $refreshTokenRepositoryMock->method('persistNewRefreshToken')->willReturnSelf();
        $refreshTokenRepositoryMock->method('getNewRefreshToken')->willReturn(new RefreshTokenEntity());
        $scope = new ScopeEntity();
        $scopeRepositoryMock = $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock();
        $scopeRepositoryMock->method('getScopeEntityByIdentifier')->willReturn($scope);
        $scopeRepositoryMock->method('finalizeScopes')->willReturnArgument(0);
        $grant = new SocialGrant($resolverMock, $refreshTokenRepositoryMock);
        $grant->setClientRepository($clientRepositoryMock);
        $grant->setAccessTokenRepository($accessTokenRepositoryMock);
        $grant->setScopeRepository($scopeRepositoryMock);
        $grant->setDefaultScope(self::DEFAULT_SCOPE);
        $grant->setPrivateKey(new CryptKey('file://' . __DIR__ . '/Stubs/private.key'));
        $serverRequest = (new ServerRequest())->withParsedBody([
            'client_id'     => 'foo',
            'client_secret' => 'bar',
            'provider'      => 'foo',
            'access_token'  => 'bar',
        ]);

        $responseType = new StubResponseType();
        $grant->respondToAccessTokenRequest($serverRequest, $responseType, new DateInterval('PT5M'));

        $this->assertInstanceOf(AccessTokenEntityInterface::class, $responseType->getAccessToken());
        $this->assertInstanceOf(RefreshTokenEntityInterface::class, $responseType->getRefreshToken());
    }

    public function testRespondToRequestNullRefreshToken()
    {
        $client = new ClientEntity();
        $clientRepositoryMock = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepositoryMock->method('getClientEntity')->willReturn($client);
        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();
        $accessTokenRepositoryMock->method('getNewToken')->willReturn(new AccessTokenEntity());
        $accessTokenRepositoryMock->method('persistNewAccessToken')->willReturnSelf();
        $resolverMock = $this->getMockBuilder(SocialGrantUserResolver::class)->getMock();
        $userEntity = new UserEntity();
        $resolverMock->method('resolve')->willReturn($userEntity);
        $scope = new ScopeEntity();
        $scopeRepositoryMock = $this->getMockBuilder(ScopeRepositoryInterface::class)->getMock();
        $scopeRepositoryMock->method('getScopeEntityByIdentifier')->willReturn($scope);
        $scopeRepositoryMock->method('finalizeScopes')->willReturnArgument(0);
        $refreshTokenRepositoryMock = $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock();
        $refreshTokenRepositoryMock->method('getNewRefreshToken')->willReturn(null);
        $grant = new SocialGrant($resolverMock, $refreshTokenRepositoryMock);
        $grant->setClientRepository($clientRepositoryMock);
        $grant->setAccessTokenRepository($accessTokenRepositoryMock);
        $grant->setScopeRepository($scopeRepositoryMock);
        $grant->setDefaultScope(self::DEFAULT_SCOPE);
        $grant->setPrivateKey(new CryptKey('file://' . __DIR__ . '/Stubs/private.key'));
        $serverRequest = (new ServerRequest())->withParsedBody([
            'client_id'     => 'foo',
            'client_secret' => 'bar',
            'provider'      => 'foo',
            'access_token'  => 'bar',
        ]);
        $responseType = new StubResponseType();
        $grant->respondToAccessTokenRequest($serverRequest, $responseType, new \DateInterval('PT5M'));
        $this->assertInstanceOf(AccessTokenEntityInterface::class, $responseType->getAccessToken());
        $this->assertNull($responseType->getRefreshToken());
    }

    public function testRespondToRequestMissingProvider()
    {
        $client = new ClientEntity();
        $clientRepositoryMock = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepositoryMock->method('getClientEntity')->willReturn($client);
        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();
        $resolverMock = $this->getMockBuilder(SocialGrantUserResolver::class)->getMock();
        $refreshTokenRepositoryMock = $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock();
        $grant = new SocialGrant($resolverMock, $refreshTokenRepositoryMock);
        $grant->setClientRepository($clientRepositoryMock);
        $grant->setAccessTokenRepository($accessTokenRepositoryMock);
        $serverRequest = (new ServerRequest())->withQueryParams([
            'client_id'     => 'foo',
            'client_secret' => 'bar',
        ]);
        $responseType = new StubResponseType();
        $this->expectException(OAuthServerException::class);
        $grant->respondToAccessTokenRequest($serverRequest, $responseType, new DateInterval('PT5M'));
    }

    public function testRespondToRequestMissingAccessToken()
    {
        $client = new ClientEntity();
        $clientRepositoryMock = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepositoryMock->method('getClientEntity')->willReturn($client);
        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();
        $resolverMock = $this->getMockBuilder(SocialGrantUserResolver::class)->getMock();
        $refreshTokenRepositoryMock = $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock();
        $grant = new SocialGrant($resolverMock, $refreshTokenRepositoryMock);
        $grant->setClientRepository($clientRepositoryMock);
        $grant->setAccessTokenRepository($accessTokenRepositoryMock);
        $serverRequest = (new ServerRequest())->withParsedBody([
            'client_id'     => 'foo',
            'client_secret' => 'bar',
            'provider'      => 'foo',
        ]);
        $responseType = new StubResponseType();
        $this->expectException(OAuthServerException::class);
        $grant->respondToAccessTokenRequest($serverRequest, $responseType, new DateInterval('PT5M'));
    }

    public function testRespondToRequestBadCredentials()
    {
        $client = new ClientEntity();
        $clientRepositoryMock = $this->getMockBuilder(ClientRepositoryInterface::class)->getMock();
        $clientRepositoryMock->method('getClientEntity')->willReturn($client);
        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();
        $resolverMock = $this->getMockBuilder(SocialGrantUserResolver::class)->getMock();
        $resolverMock->method('resolve')->willReturn(null);
        $refreshTokenRepositoryMock = $this->getMockBuilder(RefreshTokenRepositoryInterface::class)->getMock();
        $grant = new SocialGrant($resolverMock, $refreshTokenRepositoryMock);
        $grant->setClientRepository($clientRepositoryMock);
        $grant->setAccessTokenRepository($accessTokenRepositoryMock);
        $serverRequest = (new ServerRequest())->withParsedBody([
            'client_id' => 'foo',
            'client_secret' => 'bar',
            'provider'      => 'foo',
            'access_token'  => 'barx',
        ]);
        $responseType = new StubResponseType();
        $this->expectException(OAuthServerException::class);
        $this->expectExceptionCode(10);
        $grant->respondToAccessTokenRequest($serverRequest, $responseType, new DateInterval('PT5M'));
    }
}
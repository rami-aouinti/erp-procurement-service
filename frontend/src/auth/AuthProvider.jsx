import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import Keycloak from 'keycloak-js';

const keycloak = new Keycloak({
  url: import.meta.env.VITE_KEYCLOAK_URL || 'http://localhost:8081',
  realm: import.meta.env.VITE_KEYCLOAK_REALM || 'erp-procurement',
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'erp-procurement-frontend',
});

const AuthContext = createContext(null);
let initializationPromise = null;

export function AuthProvider({ children }) {
  const [initialized, setInitialized] = useState(false);
  const [authenticated, setAuthenticated] = useState(false);
  const [user, setUser] = useState(null);

  useEffect(() => {
    let active = true;
    if (!initializationPromise) {
      initializationPromise = keycloak.init({
        onLoad: 'check-sso',
        pkceMethod: 'S256',
        checkLoginIframe: false,
        responseMode: 'query',
      });
    }
    initializationPromise.then((isAuthenticated) => {
      if (!active) return;
      setAuthenticated(isAuthenticated);
      setUser(isAuthenticated ? keycloak.tokenParsed || null : null);
      setInitialized(true);
    }).catch((error) => {
      console.error('Keycloak initialization failed:', error);
      if (active) setInitialized(true);
    });
    return () => { active = false; };
  }, []);

  const login = useCallback(() => keycloak.login({ redirectUri: window.location.origin }), []);
  const logout = useCallback(() => keycloak.logout({ redirectUri: window.location.origin }), []);
  const getAccessToken = useCallback(async () => {
    if (!keycloak.authenticated) return null;
    try {
      await keycloak.updateToken(30);
      return keycloak.token || null;
    } catch (error) {
      console.error('Unable to refresh Keycloak access token:', error);
      await keycloak.login({ redirectUri: window.location.href });
      return null;
    }
  }, []);

  const roles = useMemo(() => {
    const realmRoles = user?.realm_access?.roles || [];
    const clientRoles = user?.resource_access?.['erp-procurement-api']?.roles || [];
    return [...new Set([...realmRoles, ...clientRoles])];
  }, [user]);

  const value = useMemo(() => ({
    initialized,
    authenticated,
    user,
    roles,
    login,
    logout,
    getAccessToken,
  }), [initialized, authenticated, user, roles, login, logout, getAccessToken]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider.');
  return context;
}

export async function getKeycloakAccessToken() {
  if (!keycloak.authenticated) return null;
  try {
    await keycloak.updateToken(30);
    return keycloak.token || null;
  } catch (error) {
    console.error('Unable to refresh Keycloak access token:', error);
    await keycloak.login({ redirectUri: window.location.href });
    return null;
  }
}

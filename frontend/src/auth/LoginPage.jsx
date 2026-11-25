import { ShieldCheck } from 'lucide-react';

export default function LoginPage({ onLogin, loading, error }) {
  return (
    <main className="auth-screen">
      <section className="auth-card">
        <div className="auth-mark"><ShieldCheck size={30} /></div>
        <p className="eyebrow">ERP PROCUREMENT</p>
        <h1>Welcome back</h1>
        <p className="auth-description">Sign in with your company account to access the procurement workspace.</p>
        {error && <div className="alert alert-error" role="alert">{error}</div>}
        <button className="primary-button auth-login-button" type="button" onClick={onLogin} disabled={loading}>
          {loading ? 'Connecting...' : 'Continue with Keycloak'}
        </button>
        <p className="auth-security-note">Secure sign-in · OpenID Connect · PKCE</p>
      </section>
    </main>
  );
}

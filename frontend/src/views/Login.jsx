import { useState } from 'react';
import { useAuth } from '../auth';

export default function Login({ onDone }) {
  const { login, register } = useAuth();
  const [mode, setMode] = useState('login');
  const [form, setForm] = useState({ name: '', email: '', password: '', role: 'user' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      if (mode === 'register') {
        await register({
          name: form.name,
          email: form.email,
          password: form.password,
          role: form.role,
        });
      }
      await login(form.email, form.password);
      onDone();
    } catch (err) {
      const errors = err.data?.errors;
      setError(errors ? Object.values(errors).join(' ') : err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="form" onSubmit={submit}>
      <div className="tabs">
        <button type="button" className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')}>
          Login
        </button>
        <button type="button" className={mode === 'register' ? 'active' : ''} onClick={() => setMode('register')}>
          Register
        </button>
      </div>

      {mode === 'register' && (
        <>
          <label>
            Name
            <input value={form.name} onChange={set('name')} />
          </label>
          <label>
            Role
            <select value={form.role} onChange={set('role')}>
              <option value="user">user</option>
              <option value="author">author</option>
            </select>
          </label>
        </>
      )}

      <label>
        Email
        <input type="email" value={form.email} onChange={set('email')} />
      </label>
      <label>
        Password
        <input type="password" value={form.password} onChange={set('password')} />
      </label>

      {error && <p className="error">{error}</p>}

      <button disabled={busy}>
        {busy ? '…' : mode === 'login' ? 'Login' : 'Create account & login'}
      </button>
    </form>
  );
}

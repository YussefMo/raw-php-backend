import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';

export default function AdminCategories() {
  const { token } = useAuth();
  const [categories, setCategories] = useState(null);
  const [name, setName] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  function load() {
    return api('/categories', { token })
      .then((d) => setCategories(d.data))
      .catch((e) => setError(e.message));
  }

  useEffect(() => {
    load();
  }, [token]);

  async function add(e) {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      await api('/categories', { method: 'POST', token, body: { name } });
      setName('');
      await load();
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  async function remove(id) {
    if (!window.confirm('Delete this category?')) return;
    setError('');
    try {
      await api(`/categories/${id}`, { method: 'DELETE', token });
      await load();
    } catch (err) {
      setError(err.data?.message ?? err.message);
    }
  }

  return (
    <div>
      <form className="row" onSubmit={add}>
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="New category name"
        />
        <button disabled={busy || !name.trim()}>Add</button>
      </form>

      {error && <p className="error">{error}</p>}

      {!categories ? (
        <p>Loading…</p>
      ) : (
        <ul className="rows">
          {categories.map((c) => (
            <li key={c.id}>
              <span>
                #{c.id} · {c.name}
              </span>
              <button onClick={() => remove(c.id)}>Delete</button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

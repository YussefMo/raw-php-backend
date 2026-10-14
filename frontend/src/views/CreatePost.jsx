import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';

export default function CreatePost({ onCreated }) {
  const { token } = useAuth();
  const [categories, setCategories] = useState(null);
  const [form, setForm] = useState({ title: '', body: '', category_id: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api('/categories', { token })
      .then((d) => setCategories(d.data))
      .catch((e) => setError(e.message));
  }, [token]);

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      const d = await api('/posts', {
        method: 'POST',
        token,
        body: {
          title: form.title,
          body: form.body,
          category_id: form.category_id === '' ? null : Number(form.category_id),
        },
      });
      onCreated(d.data.id);
    } catch (err) {
      const errors = err.data?.errors;
      setError(errors ? Object.values(errors).join(' ') : err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="form" onSubmit={submit}>
      <h2>New post</h2>
      <label>
        Title
        <input value={form.title} onChange={set('title')} />
      </label>
      <label>
        Body
        <textarea rows={6} value={form.body} onChange={set('body')} />
      </label>
      <label>
        Category
        <select value={form.category_id} onChange={set('category_id')} disabled={!categories}>
          <option value="">{categories ? 'Select a category…' : 'Loading…'}</option>
          {categories?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      </label>
      {error && <p className="error">{error}</p>}
      <button disabled={busy || !categories}>{busy ? 'Saving…' : 'Create'}</button>
    </form>
  );
}

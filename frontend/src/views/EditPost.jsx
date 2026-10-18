import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';

export default function EditPost({ id, onSaved, onCancel }) {
  const { token } = useAuth();
  const [form, setForm] = useState(null);
  const [categories, setCategories] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api(`/posts/${id}`)
      .then((d) =>
        setForm({
          title: d.data.title,
          body: d.data.body,
          category_id: String(d.data.category_id),
        }),
      )
      .catch((e) => setError(e.message));

    api('/categories', { token })
      .then((d) => setCategories(d.data))
      .catch((e) => setError(e.message));
  }, [id, token]);

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      await api(`/posts/${id}`, {
        method: 'PATCH',
        token,
        body: {
          title: form.title,
          body: form.body,
          category_id: Number(form.category_id),
        },
      });
      onSaved(id);
    } catch (err) {
      const errors = err.data?.errors;
      setError(errors ? Object.values(errors).join(' ') : err.message);
    } finally {
      setBusy(false);
    }
  }

  if (!form || !categories) return <p>{error || 'Loading…'}</p>;

  return (
    <form className="form" onSubmit={submit}>
      <h2>Edit post</h2>
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
        <select value={form.category_id} onChange={set('category_id')}>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      </label>
      {error && <p className="error">{error}</p>}
      <div className="row">
        <button disabled={busy}>{busy ? 'Saving…' : 'Save'}</button>
        <button type="button" onClick={onCancel}>
          Cancel
        </button>
      </div>
    </form>
  );
}

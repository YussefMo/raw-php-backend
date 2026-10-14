import { useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';

export default function CreatePost({ onCreated }) {
  const { token } = useAuth();
  const [form, setForm] = useState({ title: '', body: '', category_id: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      const body = {
        title: form.title,
        body: form.body,
        category_id: form.category_id === '' ? null : Number(form.category_id),
      };
      const d = await api('/posts', { method: 'POST', body, token });
      onCreated(d.data.id);
    } catch (err) {
      setError(err.data?.errors?.title ?? err.message);
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
        Category id
        <input value={form.category_id} onChange={set('category_id')} placeholder="1" />
      </label>
      {error && <p className="error">{error}</p>}
      <button disabled={busy}>{busy ? 'Saving…' : 'Create'}</button>
    </form>
  );
}

import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';

export default function MyPosts({ onOpen, onEdit }) {
  const { token } = useAuth();
  const [posts, setPosts] = useState(null);
  const [error, setError] = useState('');

  function load() {
    return api('/me/posts', { token })
      .then((d) => setPosts(d.data))
      .catch((e) => setError(e.message));
  }

  useEffect(() => {
    load();
  }, [token]);

  function edit(e, id) {
    e.stopPropagation();
    onEdit(id);
  }

  async function remove(e, id) {
    e.stopPropagation();
    if (!window.confirm('Delete this post?')) return;
    setError('');
    try {
      await api(`/posts/${id}`, { method: 'DELETE', token });
      await load();
    } catch (err) {
      setError(err.message);
    }
  }

  if (error) return <p className="error">{error}</p>;
  if (!posts) return <p>Loading…</p>;
  if (posts.length === 0) return <p>You haven't written any posts yet.</p>;

  return (
    <ul className="posts">
      {posts.map((p) => (
        <li key={p.id}>
          <div className="post-body" onClick={() => onOpen(p.id)}>
            <h3>{p.title}</h3>
            <p className="meta">
              {p.category_name ?? 'uncategorized'} · {p.created_at}
            </p>
            <p className="excerpt">{(p.body ?? '').slice(0, 140)}</p>
          </div>
          <div className="row-actions">
            <button onClick={(e) => edit(e, p.id)}>Edit</button>
            <button onClick={(e) => remove(e, p.id)}>Delete</button>
          </div>
        </li>
      ))}
    </ul>
  );
}

import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';

export default function Posts({ onOpen, onEdit }) {
  const { user, token } = useAuth();
  const [posts, setPosts] = useState(null);
  const [error, setError] = useState('');

  const isAdmin = user?.role === 'admin';

  function load() {
    return api('/')
      .then((d) => setPosts(d.data))
      .catch((e) => setError(e.message));
  }

  useEffect(() => {
    load();
  }, []);

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
  if (!posts) return <p>Loading posts…</p>;
  if (posts.length === 0) return <p>No posts yet.</p>;

  return (
    <ul className="posts">
      {posts.map((p) => (
        <li key={p.id}>
          <div className="post-body" onClick={() => onOpen(p.id)}>
            <h3>{p.title}</h3>
            <p className="meta">
              {p.author} · {p.category_name ?? 'uncategorized'} · {p.created_at}
            </p>
            <p className="excerpt">{(p.body ?? '').slice(0, 140)}</p>
          </div>
          {isAdmin && (
            <div className="row-actions">
              <button onClick={(e) => edit(e, p.id)}>Edit</button>
              <button onClick={(e) => remove(e, p.id)}>Delete</button>
            </div>
          )}
        </li>
      ))}
    </ul>
  );
}

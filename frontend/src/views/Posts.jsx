import { useEffect, useState } from 'react';
import { api } from '../api';

export default function Posts({ onOpen }) {
  const [posts, setPosts] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api('/')
      .then((d) => setPosts(d.data))
      .catch((e) => setError(e.message));
  }, []);

  if (error) return <p className="error">{error}</p>;
  if (!posts) return <p>Loading posts…</p>;
  if (posts.length === 0) return <p>No posts yet.</p>;

  return (
    <ul className="posts">
      {posts.map((p) => (
        <li key={p.id} onClick={() => onOpen(p.id)}>
          <h3>{p.title}</h3>
          <p className="meta">
            {p.author} · {p.category_name ?? 'uncategorized'} · {p.created_at}
          </p>
          <p className="excerpt">{(p.body ?? '').slice(0, 140)}</p>
        </li>
      ))}
    </ul>
  );
}

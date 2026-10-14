import { useEffect, useState } from 'react';
import { api } from '../api';

export default function PostDetail({ id, onBack }) {
  const [post, setPost] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api(`/posts/${id}`)
      .then((d) => setPost(d.data))
      .catch((e) => setError(e.message));
  }, [id]);

  return (
    <article className="detail">
      <button onClick={onBack}>← Back</button>
      {error && <p className="error">{error}</p>}
      {!post && !error && <p>Loading…</p>}
      {post && (
        <>
          <h2>{post.title}</h2>
          <p className="meta">
            {post.author} · {post.category_name ?? 'uncategorized'} · {post.created_at}
          </p>
          <p>{post.body}</p>
        </>
      )}
    </article>
  );
}

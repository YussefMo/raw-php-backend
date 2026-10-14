import { useState } from 'react';
import { useAuth } from './auth';
import Posts from './views/Posts';
import PostDetail from './views/PostDetail';
import CreatePost from './views/CreatePost';
import AdminUsers from './views/AdminUsers';
import AdminCategories from './views/AdminCategories';
import Login from './views/Login';

export default function App() {
  const { user, loading, logout } = useAuth();
  const [view, setView] = useState({ name: 'posts' });

  const canPublish = Boolean(user) && ['admin', 'author'].includes(user.role);
  const isAdmin = user?.role === 'admin';

  if (loading) return <div className="center">Loading…</div>;

  const go = (name, extra = {}) => setView({ name, ...extra });

  return (
    <div className="app">
      <header>
        <h1 onClick={() => go('posts')}>1st-api</h1>

        <nav>
          <button className={view.name === 'posts' ? 'active' : ''} onClick={() => go('posts')}>
            Posts
          </button>
          {canPublish && (
            <button className={view.name === 'create' ? 'active' : ''} onClick={() => go('create')}>
              New Post
            </button>
          )}
          {isAdmin && (
            <button className={view.name === 'users' ? 'active' : ''} onClick={() => go('users')}>
              Users
            </button>
          )}
          {isAdmin && (
            <button
              className={view.name === 'categories' ? 'active' : ''}
              onClick={() => go('categories')}
            >
              Categories
            </button>
          )}
        </nav>

        <div className="spacer" />

        {user ? (
          <div className="me">
            <span>{user.email}</span>
            <span className={`role role-${user.role}`}>{user.role}</span>
            <button onClick={logout}>Logout</button>
          </div>
        ) : (
          <button className={view.name === 'login' ? 'active' : ''} onClick={() => go('login')}>
            Login / Register
          </button>
        )}
      </header>

      <main>
        {view.name === 'posts' && <Posts onOpen={(id) => go('post', { id })} />}
        {view.name === 'post' && <PostDetail id={view.id} onBack={() => go('posts')} />}
        {view.name === 'create' && <CreatePost onCreated={(id) => go('post', { id })} />}
        {view.name === 'users' && <AdminUsers />}
        {view.name === 'categories' && <AdminCategories />}
        {view.name === 'login' && <Login onDone={() => go('posts')} />}
      </main>
    </div>
  );
}

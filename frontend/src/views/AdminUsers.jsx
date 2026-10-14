import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';

export default function AdminUsers() {
  const { token } = useAuth();
  const [users, setUsers] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api('/admin/users', { token })
      .then((d) => setUsers(d.data))
      .catch((e) => setError(e.message));
  }, [token]);

  if (error) return <p className="error">{error}</p>;
  if (!users) return <p>Loading users…</p>;

  return (
    <table className="users">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        {users.map((u) => (
          <tr key={u.id}>
            <td>{u.id}</td>
            <td>{u.name}</td>
            <td>{u.email}</td>
            <td>
              <span className={`role role-${u.role}`}>{u.role}</span>
            </td>
            <td>{u.created_at}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

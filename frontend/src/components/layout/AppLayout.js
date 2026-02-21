import React from 'react';
import { Container, Navbar, Nav, Button } from 'react-bootstrap';
import { useDispatch, useSelector } from 'react-redux';
import { logoutUser } from '../../store/slices/authSlice';
import { useNavigate } from 'react-router-dom';

function AppLayout({ children }) {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { user } = useSelector((state) => state.auth);

  const handleLogout = async () => {
    await dispatch(logoutUser());
    navigate('/login');
  };

  return (
    <div className="d-flex flex-column min-vh-100">
      <Navbar bg="dark" variant="dark" expand="lg">
        <Container>
          <Navbar.Brand href="/home">Onboarding Portal</Navbar.Brand>
          <Navbar.Toggle />
          <Navbar.Collapse className="justify-content-end">
            <Nav>
              {user && (
                <>
                  <Navbar.Text className="me-3">
                    {user.email}
                  </Navbar.Text>
                  <Button variant="outline-light" size="sm" onClick={handleLogout}>
                    Logout
                  </Button>
                </>
              )}
            </Nav>
          </Navbar.Collapse>
        </Container>
      </Navbar>
      <Container className="flex-grow-1 py-4">
        {children}
      </Container>
      <footer className="bg-light text-center py-3 mt-auto">
        <small className="text-muted">Client Onboarding Portal</small>
      </footer>
    </div>
  );
}

export default AppLayout;

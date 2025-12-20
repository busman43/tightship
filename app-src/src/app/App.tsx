import { useState } from "react";
import { Sidebar } from "./components/Sidebar";
import { CommandCenter } from "./components/CommandCenter";
import { FileInbox } from "./components/FileInbox";
import { DocumentView } from "./components/DocumentView";
import { ProjectsView } from "./components/ProjectsView";
import { ProposalsView } from "./components/ProposalsView";
import { CalendarView } from "./components/CalendarView";
import { LoginPage } from "./components/Auth/LoginPage";
import { RegisterPage } from "./components/Auth/RegisterPage";

type View = "command-center" | "inbox" | "spaces" | "projects" | "proposals" | "files" | "templates" | "crm" | "reports" | "calendar";

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [authView, setAuthView] = useState<"login" | "register">("login");
  const [currentView, setCurrentView] = useState<View>("command-center");
  const [selectedDocument, setSelectedDocument] = useState<string | null>(null);

  const handleLogin = () => {
    setIsAuthenticated(true);
  };

  const handleRegister = () => {
    setIsAuthenticated(true);
  };

  const handleViewChange = (view: View) => {
    setCurrentView(view);
    setSelectedDocument(null);
  };

  const handleDocumentSelect = (docId: string) => {
    setSelectedDocument(docId);
  };

  const handleBackToInbox = () => {
    setSelectedDocument(null);
  };

  // Show auth pages if not authenticated
  if (!isAuthenticated) {
    if (authView === "login") {
      return (
        <LoginPage 
          onLogin={handleLogin}
          onSwitchToRegister={() => setAuthView("register")}
        />
      );
    } else {
      return (
        <RegisterPage 
          onRegister={handleRegister}
          onSwitchToLogin={() => setAuthView("login")}
        />
      );
    }
  }

  return (
    <div className="flex h-screen bg-[#F5F7FA]">
      <Sidebar currentView={currentView} onViewChange={handleViewChange} />
      
      <main className="flex-1 overflow-hidden">
        {selectedDocument ? (
          <DocumentView documentId={selectedDocument} onBack={handleBackToInbox} />
        ) : (
          <>
            {currentView === "command-center" && <CommandCenter onNavigate={handleViewChange} />}
            {currentView === "inbox" && <FileInbox onDocumentSelect={handleDocumentSelect} />}
            {currentView === "projects" && <ProjectsView />}
            {currentView === "proposals" && <ProposalsView onDocumentSelect={handleDocumentSelect} />}
            {currentView === "calendar" && <CalendarView />}
            {currentView === "files" && <div className="p-8">Files View (Coming Soon)</div>}
            {currentView === "templates" && <div className="p-8">Templates View (Coming Soon)</div>}
            {currentView === "crm" && <div className="p-8">CRM View (Coming Soon)</div>}
            {currentView === "reports" && <div className="p-8">Reports View (Coming Soon)</div>}
          </>
        )}
      </main>
    </div>
  );
}